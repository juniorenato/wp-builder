<?php

/**
 * Front-end performance toggles.
 *
 * @author  Renato Rodrigues Jr <juniorenato@msn.com>
 * @license GPL-3.0-or-later
 * @package WPB\Performance
 */

namespace WPB\Performance;

use WPB\Notices;

/**
 * Disables unused head output and slows a few background WordPress tasks.
 *
 * Pass the features to enable. An empty constructor changes nothing.
 * `false` skips that entry. A bare method name uses the method default.
 *
 * @since  0.10.0
 * @author Renato Rodrigues Jr <juniorenato@msn.com>
 */
class Performance
{
    /**
     * Features already hooked on this instance.
     *
     * @since 0.10.0
     *
     * @var array<string, true>
     */
    private array $active = [];

    /**
     * Heartbeat interval in seconds.
     *
     * @since 0.10.0
     */
    private int $heartbeatInterval = 60;

    /**
     * Revisions kept for post types that support them.
     *
     * @since 0.10.0
     */
    private int $revisionLimit = 5;

    /**
     * Days before trashed posts and comments are deleted.
     *
     * @since 0.10.0
     */
    private int $trashDays = 7;

    /**
     * Page cache started by this instance.
     *
     * @since 0.10.0
     */
    private ?PageCache $cache = null;

    /**
     * Enables the given performance features.
     *
     * @since 0.10.0
     *
     * @param array<int|string, bool|int|string> $features Method names, or names mapped to an argument.
     */
    public function __construct(array $features = [])
    {
        foreach ($features as $feature => $argument) {
            if (is_int($feature)) {
                $feature = $argument;
                $argument = true;
            }

            if ($argument === false || !is_string($feature)) {
                continue;
            }

            $this->activate($feature, $argument);
        }
    }

    /**
     * Removes emoji scripts, styles, mail rewriting, and the emoji CDN hint.
     *
     * @since 0.10.0
     *
     * @see https://developer.wordpress.org/reference/functions/print_emoji_detection_script/
     * @see https://developer.wordpress.org/reference/functions/wp_enqueue_emoji_styles/
     */
    public function disableEmoji(): void
    {
        if (!$this->once('disableEmoji')) {
            return;
        }

        remove_action('wp_head', 'print_emoji_detection_script', 7);
        remove_action('embed_head', 'print_emoji_detection_script');
        remove_action('admin_print_scripts', 'print_emoji_detection_script');
        remove_action('wp_print_styles', 'print_emoji_styles');
        remove_action('admin_print_styles', 'print_emoji_styles');
        remove_action('wp_enqueue_scripts', 'wp_enqueue_emoji_styles');
        remove_action('enqueue_embed_scripts', 'wp_enqueue_emoji_styles');
        remove_filter('the_content_feed', 'wp_staticize_emoji');
        remove_filter('comment_text_rss', 'wp_staticize_emoji');
        remove_filter('wp_mail', 'wp_staticize_emoji_for_email');
        add_filter('tiny_mce_plugins', [$this, 'removeEmojiPlugin']);
        add_filter('wp_resource_hints', [$this, 'removeEmojiDnsPrefetch'], 10, 2);
    }

    /**
     * Removes the emoji plugin from TinyMCE.
     *
     * @since 0.10.0
     *
     * @param string[] $plugins TinyMCE plugin slugs.
     *
     * @return string[]
     */
    public function removeEmojiPlugin(array $plugins): array
    {
        return array_values(array_diff($plugins, ['wpemoji']));
    }

    /**
     * Drops resource hints that point at the emoji CDN.
     *
     * Other `s.w.org` hints stay. Current WordPress also hosts fonts there.
     *
     * @since 0.10.0
     *
     * @param array<int, mixed> $urls         Hint URLs.
     * @param string            $relationType Relation type such as `dns-prefetch`.
     *
     * @return array<int, mixed>
     */
    public function removeEmojiDnsPrefetch(array $urls, string $relationType): array
    {
        if ($relationType !== 'dns-prefetch' && $relationType !== 'preconnect') {
            return $urls;
        }

        return array_values(array_filter(
            $urls,
            static function (mixed $url): bool {
                $href = is_array($url) ? (string) ($url['href'] ?? '') : (string) $url;

                return !str_contains($href, 's.w.org/images/core/emoji');
            }
        ));
    }

    /**
     * Removes RSD, WLW, generator, shortlink, and adjacent-post links from `wp_head`.
     *
     * @since 0.10.0
     *
     * @see https://developer.wordpress.org/reference/functions/wp_generator/
     */
    public function cleanHead(): void
    {
        if (!$this->once('cleanHead')) {
            return;
        }

        remove_action('wp_head', 'rsd_link');
        remove_action('wp_head', 'wlwmanifest_link');
        remove_action('wp_head', 'wp_generator');
        remove_action('wp_head', 'wp_shortlink_wp_head', 10);
        remove_action('wp_head', 'adjacent_posts_rel_link_wp_head', 10);
    }

    /**
     * Stops WordPress from enqueueing `wp-embed` and printing oEmbed discovery links.
     *
     * @since 0.10.0
     *
     * @see https://developer.wordpress.org/reference/functions/wp_oembed_add_host_js/
     * @see https://developer.wordpress.org/reference/functions/wp_maybe_enqueue_oembed_host_js/
     */
    public function disableEmbeds(): void
    {
        if (!$this->once('disableEmbeds')) {
            return;
        }

        remove_action('wp_head', 'wp_oembed_add_discovery_links', 4);
        remove_action('wp_head', 'wp_oembed_add_discovery_links');
        remove_action('wp_head', 'wp_oembed_add_host_js');
        add_action('wp_enqueue_scripts', [$this, 'dequeueEmbed'], 100);
        add_action('wp_footer', [$this, 'dequeueEmbed'], 1);
    }

    /**
     * Dequeues and deregisters the embed host script.
     *
     * @since 0.10.0
     *
     * @see https://developer.wordpress.org/reference/functions/wp_dequeue_script/
     */
    public function dequeueEmbed(): void
    {
        wp_dequeue_script('wp-embed');
        wp_deregister_script('wp-embed');
    }

    /**
     * Sets the Heartbeat interval.
     *
     * WordPress accepts 1 to 3600 seconds and then converts that value to
     * milliseconds. A value outside the range falls back to 60.
     *
     * @since 0.10.0
     *
     * @param int $seconds Interval in seconds.
     *
     * @see https://developer.wordpress.org/reference/hooks/heartbeat_settings/
     */
    public function heartbeat(int $seconds = 60): void
    {
        $this->heartbeatInterval = ($seconds >= 1 && $seconds <= 3600) ? $seconds : 60;

        if ($this->once('heartbeat')) {
            add_filter('heartbeat_settings', [$this, 'heartbeatSettings'], 20);
        }
    }

    /**
     * Applies the configured Heartbeat interval.
     *
     * @since 0.10.0
     *
     * @param array<string, mixed> $settings Heartbeat settings.
     *
     * @return array<string, mixed>
     */
    public function heartbeatSettings(array $settings): array
    {
        $settings['interval'] = $this->heartbeatInterval;

        return $settings;
    }

    /**
     * Limits stored revisions for post types that support them.
     *
     * `WP_POST_REVISIONS` is defined before the theme loads, so this uses the
     * `wp_revisions_to_keep` filter. Extra revisions are removed the next time
     * that post is saved. `0` disables revisions. A constant set to `0` or
     * `false` stays off.
     *
     * @since 0.10.0
     *
     * @param int $limit Maximum revisions to keep.
     *
     * @see https://developer.wordpress.org/reference/hooks/wp_revisions_to_keep/
     */
    public function revisions(int $limit = 5): void
    {
        $this->revisionLimit = $limit >= 0 ? $limit : 5;

        if ($this->once('revisions')) {
            add_filter('wp_revisions_to_keep', [$this, 'revisionsToKeep'], 10, 2);
        }
    }

    /**
     * Returns the revision cap for a post type that supports revisions.
     *
     * @since 0.10.0
     *
     * @param int      $number Revisions requested by WordPress.
     * @param \WP_Post $post   Post being saved.
     *
     * @return int
     */
    public function revisionsToKeep(int $number, \WP_Post $post): int
    {
        if ($number === 0 || !post_type_supports($post->post_type, 'revisions')) {
            return 0;
        }

        return $this->revisionLimit;
    }

    /**
     * Deletes trashed posts and comments after the given number of days.
     *
     * `EMPTY_TRASH_DAYS` is already defined when the theme loads. This replaces
     * the `wp_scheduled_delete` callback. Auto-drafts stay on their own cron.
     * `0` deletes trash on the next daily run.
     *
     * @since 0.10.0
     *
     * @param int $days Days to keep trash.
     *
     * @see https://developer.wordpress.org/reference/functions/wp_scheduled_delete/
     */
    public function emptyTrash(int $days = 7): void
    {
        $this->trashDays = $days >= 0 ? $days : 7;
        $this->replaceTrashCleanup();

        if ($this->once('emptyTrash')) {
            add_action('init', [$this, 'replaceTrashCleanup'], 0);
        }
    }

    /**
     * Swaps the core trash callback for {@see self::deleteExpiredTrash()}.
     *
     * @since 0.10.0
     */
    public function replaceTrashCleanup(): void
    {
        remove_action('wp_scheduled_delete', 'wp_scheduled_delete');

        if (!has_action('wp_scheduled_delete', [$this, 'deleteExpiredTrash'])) {
            add_action('wp_scheduled_delete', [$this, 'deleteExpiredTrash']);
        }
    }

    /**
     * Deletes trash older than the configured number of days.
     *
     * Same queries as `wp_scheduled_delete()`, with the day count from this class.
     *
     * @since 0.10.0
     *
     * @global \wpdb $wpdb WordPress database abstraction.
     */
    public function deleteExpiredTrash(): void
    {
        global $wpdb;

        $deleteTimestamp = time() - (DAY_IN_SECONDS * $this->trashDays);
        $posts = $wpdb->get_results(
            $wpdb->prepare(
                "SELECT post_id FROM {$wpdb->postmeta} WHERE meta_key = '_wp_trash_meta_time' AND meta_value < %d",
                $deleteTimestamp
            ),
            ARRAY_A
        );

        foreach ((array) $posts as $post) {
            $postId = (int) ($post['post_id'] ?? 0);

            if ($postId === 0) {
                continue;
            }

            $trashed = get_post($postId);

            if (!$trashed || $trashed->post_status !== 'trash') {
                delete_post_meta($postId, '_wp_trash_meta_status');
                delete_post_meta($postId, '_wp_trash_meta_time');
                continue;
            }

            wp_delete_post($postId);
        }

        $comments = $wpdb->get_results(
            $wpdb->prepare(
                "SELECT comment_id FROM {$wpdb->commentmeta} WHERE meta_key = '_wp_trash_meta_time' AND meta_value < %d",
                $deleteTimestamp
            ),
            ARRAY_A
        );

        foreach ((array) $comments as $comment) {
            $commentId = (int) ($comment['comment_id'] ?? 0);

            if ($commentId === 0) {
                continue;
            }

            $trashed = get_comment($commentId);

            if (!$trashed || $trashed->comment_approved !== 'trash') {
                delete_comment_meta($commentId, '_wp_trash_meta_time');
                delete_comment_meta($commentId, '_wp_trash_meta_status');
                continue;
            }

            wp_delete_comment($trashed);
        }
    }

    /**
     * Stores new intermediate sizes as AVIF, or WebP when AVIF is unavailable.
     *
     * The uploaded original stays in its original format. WordPress already
     * adds `srcset`, `loading="lazy"`, and `decoding="async"`, and it keeps
     * `fetchpriority="high"` off the lazy path. This method fills those
     * attributes only when core left them off and the image has width and height.
     *
     * @since 0.10.0
     *
     * @see https://developer.wordpress.org/reference/hooks/image_editor_output_format/
     * @see https://developer.wordpress.org/reference/hooks/wp_content_img_tag/
     */
    public function images(): void
    {
        if (!$this->once('images')) {
            return;
        }

        add_filter('image_editor_output_format', [$this, 'imageOutputFormat']);
        add_filter('wp_get_attachment_image_attributes', [$this, 'optimizeAttachmentAttributes']);
        add_filter('wp_content_img_tag', [$this, 'optimizeImageTag']);
    }

    /**
     * Maps JPEG and PNG output to AVIF or WebP.
     *
     * @since 0.10.0
     *
     * @param array<string, string> $formats Source mime type to output mime type.
     *
     * @return array<string, string>
     */
    public function imageOutputFormat(array $formats): array
    {
        if (wp_image_editor_supports(['mime_type' => 'image/avif'])) {
            $target = 'image/avif';
        } elseif (wp_image_editor_supports(['mime_type' => 'image/webp'])) {
            $target = 'image/webp';
        } else {
            return $formats;
        }

        $formats['image/jpeg'] = $target;
        $formats['image/png'] = $target;

        return $formats;
    }

    /**
     * Adds lazy loading and async decoding to an attachment image.
     *
     * @since 0.10.0
     *
     * @param array<string, string> $attributes Image attributes.
     *
     * @return array<string, string>
     */
    public function optimizeAttachmentAttributes(array $attributes): array
    {
        if (($attributes['fetchpriority'] ?? '') === 'high') {
            return $attributes;
        }

        if (!isset($attributes['decoding'])) {
            $attributes['decoding'] = 'async';
        }

        if (
            !isset($attributes['loading'])
            && isset($attributes['width'], $attributes['height'])
        ) {
            $attributes['loading'] = 'lazy';
        }

        return $attributes;
    }

    /**
     * Adds lazy loading and async decoding to an image tag in content.
     *
     * @since 0.10.0
     *
     * @param string $image Image tag.
     *
     * @return string
     */
    public function optimizeImageTag(string $image): string
    {
        if (!class_exists(\WP_HTML_Tag_Processor::class)) {
            return $image;
        }

        $processor = new \WP_HTML_Tag_Processor($image);

        if (!$processor->next_tag('IMG')) {
            return $image;
        }

        if ($processor->get_attribute('fetchpriority') === 'high') {
            return $image;
        }

        if ($processor->get_attribute('decoding') === null) {
            $processor->set_attribute('decoding', 'async');
        }

        if (
            $processor->get_attribute('loading') === null
            && $processor->get_attribute('width') !== null
            && $processor->get_attribute('height') !== null
        ) {
            $processor->set_attribute('loading', 'lazy');
        }

        return $processor->get_updated_html();
    }

    /**
     * Starts the page cache and adds the Tools screen that controls it.
     *
     * Nothing is stored or served until this method runs. The HTML file name
     * is the MD5 of the normalized URL, not the page slug. The URL itself is
     * kept in a sidecar file so the Tools screen can list it. Saving a public
     * post removes that post URL, the post type archive and its pagination,
     * and any pages linked to the post type. `$seconds` is how long a file
     * stays valid. A value below 1 uses 3600.
     *
     * @since 0.10.0
     *
     * @param int $seconds Cache lifetime in seconds.
     */
    public function enableCache(int $seconds = 3600): void
    {
        $seconds = $seconds >= 1 ? $seconds : 3600;

        if (!$this->cache instanceof PageCache) {
            $this->cache = new PageCache($seconds);
            $this->cache->register();

            return;
        }

        $this->cache->ttl($seconds);
    }

    /**
     * Hooks one named feature.
     *
     * @since 0.10.0
     *
     * @param string $feature  Method name.
     * @param mixed  $argument Constructor argument for that method.
     */
    private function activate(string $feature, mixed $argument): void
    {
        match ($feature) {
            'disableEmoji' => $this->disableEmoji(),
            'cleanHead' => $this->cleanHead(),
            'disableEmbeds' => $this->disableEmbeds(),
            'heartbeat' => $this->heartbeat(is_int($argument) ? $argument : 60),
            'revisions' => $this->revisions(is_int($argument) ? $argument : 5),
            'emptyTrash' => $this->emptyTrash(is_int($argument) ? $argument : 7),
            'images' => $this->images(),
            'enableCache' => $this->enableCache(is_int($argument) ? $argument : 3600),
            default => Notices::error(sprintf(
                'Unknown performance feature "%s".',
                $feature
            )),
        };
    }

    /**
     * Records a feature the first time it is enabled.
     *
     * @since 0.10.0
     *
     * @param string $feature Feature name.
     *
     * @return bool True when this call should register hooks.
     */
    private function once(string $feature): bool
    {
        if (isset($this->active[$feature])) {
            return false;
        }

        $this->active[$feature] = true;

        return true;
    }
}
