<?php

/**
 * Front-end performance toggles.
 *
 * @author  Renato Rodrigues Jr <juniorenato@msn.com>
 * @license GPL-3.0-or-later
 * @package WPB\Performance
 */

namespace WPB\Performance;

use WPB\Builder;
use WPB\Notices;

/**
 * Disables unused head output and slows a few background WordPress tasks.
 *
 * Pass the features to enable. An empty constructor changes nothing.
 * `false` skips that entry. A bare method name uses the method default.
 * `enableAll()` turns every feature on with those defaults.
 *
 * @since  0.10.0
 * @author Renato Rodrigues Jr <juniorenato@msn.com>
 */
class Performance
{
    /**
     * Mime types rebuilt by the dashboard regenerate button.
     *
     * @since 0.10.0
     *
     * @var list<string>
     */
    private const array REGENERATED_IMAGE_TYPES = [
        'image/jpeg',
        'image/png',
        'image/webp',
        'image/avif',
    ];

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
     * Longest edge kept for a new upload, in pixels.
     *
     * @since 0.10.0
     */
    private int $imageMaxEdge = 2048;

    /**
     * Quality used when saving AVIF or WebP.
     *
     * @since 0.10.0
     */
    private int $imageQuality = 75;

    /**
     * Attachment image size preloaded as the LCP candidate.
     *
     * @since 0.10.0
     */
    private string $lcpImageSize = 'large';

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
     * Enables every image optimization with its default argument.
     *
     * A dimension, quality, or LCP size already set on this instance stays.
     * The defaults are 2048 pixels, quality 75, and the `large` size.
     *
     * @since 0.10.0
     */
    public function images(): void
    {
        if (!$this->once('images')) {
            return;
        }

        if (!isset($this->active['limitImageDimension'])) {
            $this->limitImageDimension();
        }

        if (!isset($this->active['stripImageMetadata'])) {
            $this->stripImageMetadata();
        }

        if (!isset($this->active['modernImageFormat'])) {
            $this->modernImageFormat();
        }

        if (!isset($this->active['imageQuality'])) {
            $this->imageQuality();
        }

        if (!isset($this->active['disableExtraImageSizes'])) {
            $this->disableExtraImageSizes();
        }

        if (!isset($this->active['capImageDensity'])) {
            $this->capImageDensity();
        }

        if (!isset($this->active['preferModernImage'])) {
            $this->preferModernImage();
        }

        if (!isset($this->active['responsiveImageSizes'])) {
            $this->responsiveImageSizes();
        }

        if (!isset($this->active['prioritizeLcpImage'])) {
            $this->prioritizeLcpImage();
        }

        if (!isset($this->active['lazyLoadImages'])) {
            $this->lazyLoadImages();
        }

        $this->registerImageDashboard();
    }

    /**
     * Caps the scaled full-size image at the given edge length.
     *
     * WordPress stores a `-scaled` file when the upload is larger and keeps
     * the original for editing. A value below 1 uses 2048.
     *
     * @since 0.10.0
     *
     * @param int $pixels Longest edge in pixels.
     *
     * @see https://developer.wordpress.org/reference/hooks/big_image_size_threshold/
     */
    public function limitImageDimension(int $pixels = 2048): void
    {
        $this->imageMaxEdge = $pixels >= 1 ? $pixels : 2048;

        if ($this->once('limitImageDimension')) {
            add_filter('big_image_size_threshold', [$this, 'bigImageSizeThreshold']);
        }
    }

    /**
     * Returns the configured longest edge.
     *
     * @since 0.10.0
     *
     * @return int
     */
    public function bigImageSizeThreshold(): int
    {
        return $this->imageMaxEdge;
    }

    /**
     * Removes camera metadata from a new JPEG, PNG, WebP, or AVIF upload.
     *
     * JPEG uploads are rotated from the EXIF orientation first. Imagick also
     * converts JPEG to sRGB before the profile is removed. WebP and AVIF are
     * stripped only when Imagick is available, so they are not encoded twice.
     *
     * @since 0.10.0
     *
     * @see https://developer.wordpress.org/reference/hooks/wp_handle_upload/
     */
    public function stripImageMetadata(): void
    {
        if (!$this->once('stripImageMetadata')) {
            return;
        }

        add_filter('wp_handle_upload', [$this, 'stripUploadedImage']);
    }

    /**
     * Strips metadata from one uploaded image.
     *
     * @since 0.10.0
     *
     * @param array<string, mixed> $upload Upload result with `file`, `url`, and `type`.
     *
     * @return array<string, mixed>
     */
    public function stripUploadedImage(array $upload): array
    {
        $file = $upload['file'] ?? '';
        $mime = $upload['type'] ?? '';

        if (!is_string($file) || !is_string($mime) || !is_file($file)) {
            return $upload;
        }

        if (!in_array($mime, ['image/jpeg', 'image/png', 'image/webp', 'image/avif'], true)) {
            return $upload;
        }

        $this->stripImageFile($file, $mime);

        return $upload;
    }

    /**
     * Saves new JPEG and PNG sizes as AVIF, or WebP when AVIF is unavailable.
     *
     * Generated sizes use the modern format. An uploaded AVIF or WebP is left
     * as it is. A JPEG or PNG that is already within the size threshold also
     * gets one full-size modern file next to the original.
     *
     * @since 0.10.0
     *
     * @see https://developer.wordpress.org/reference/hooks/image_editor_output_format/
     * @see https://developer.wordpress.org/reference/hooks/wp_generate_attachment_metadata/
     */
    public function modernImageFormat(): void
    {
        if (!$this->once('modernImageFormat')) {
            return;
        }

        add_filter('image_editor_output_format', [$this, 'imageOutputFormat']);
        add_filter('wp_generate_attachment_metadata', [$this, 'addModernFullImage'], 10, 2);
    }

    /**
     * Sets the quality used for AVIF and WebP output.
     *
     * JPEG and PNG keep the editor default. A value outside 1–100 uses 75.
     *
     * @since 0.10.0
     *
     * @param int $quality Encoder quality from 1 to 100.
     *
     * @see https://developer.wordpress.org/reference/hooks/wp_editor_set_quality/
     */
    public function imageQuality(int $quality = 75): void
    {
        $this->imageQuality = ($quality >= 1 && $quality <= 100) ? $quality : 75;

        if ($this->once('imageQuality')) {
            add_filter('wp_editor_set_quality', [$this, 'editorImageQuality'], 10, 2);
        }
    }

    /**
     * Returns the modern-format quality and leaves other mime types unchanged.
     *
     * @since 0.10.0
     *
     * @param int    $quality  Quality requested by the editor.
     * @param string $mimeType Mime type being saved.
     *
     * @return int
     */
    public function editorImageQuality(int $quality, string $mimeType = ''): int
    {
        if ($mimeType === 'image/avif' || $mimeType === 'image/webp') {
            return $this->imageQuality;
        }

        return $quality;
    }

    /**
     * Drops intermediate sizes beyond thumbnail, medium, and large.
     *
     * Thumbnail, medium, and large stay. `medium_large`, 1536, and 2048 are removed.
     *
     * @since 0.10.0
     *
     * @see https://developer.wordpress.org/reference/hooks/intermediate_image_sizes_advanced/
     */
    public function disableExtraImageSizes(): void
    {
        if (!$this->once('disableExtraImageSizes')) {
            return;
        }

        add_filter('intermediate_image_sizes_advanced', [$this, 'removeExtraImageSizes']);
    }

    /**
     * Removes the extra default sizes from a size list.
     *
     * @since 0.10.0
     *
     * @param array<string, mixed> $sizes Registered intermediate sizes.
     *
     * @return array<string, mixed>
     */
    public function removeExtraImageSizes(array $sizes): array
    {
        unset($sizes['medium_large'], $sizes['1536x1536'], $sizes['2048x2048']);

        return $sizes;
    }

    /**
     * Drops srcset candidates wider than twice the requested image width.
     *
     * The smallest candidate stays when every source is wider than that cap.
     *
     * @since 0.10.0
     *
     * @see https://developer.wordpress.org/reference/hooks/wp_calculate_image_srcset/
     */
    public function capImageDensity(): void
    {
        if (!$this->once('capImageDensity')) {
            return;
        }

        add_filter('wp_calculate_image_srcset', [$this, 'capImageSrcsetDensity'], 11, 2);
    }

    /**
     * Removes srcset sources above the 2x cap.
     *
     * @since 0.10.0
     *
     * @param array<int, array<string, mixed>> $sources   Sources keyed by width.
     * @param array<int, int>                  $sizeArray Requested width and height.
     *
     * @return array<int, array<string, mixed>>
     */
    public function capImageSrcsetDensity(array $sources, array $sizeArray): array
    {
        $requested = (int) ($sizeArray[0] ?? 0);

        if ($requested < 1 || $sources === []) {
            return $sources;
        }

        $max = $requested * 2;
        $kept = [];

        foreach ($sources as $width => $source) {
            if ((int) $width <= $max) {
                $kept[(int) $width] = $source;
            }
        }

        if ($kept !== []) {
            return $kept;
        }

        $smallest = null;

        foreach ($sources as $width => $source) {
            $width = (int) $width;

            if ($smallest === null || $width < $smallest) {
                $smallest = $width;
            }
        }

        if ($smallest === null || !isset($sources[$smallest])) {
            return $sources;
        }

        return [$smallest => $sources[$smallest]];
    }

    /**
     * Points front-end image URLs at an existing AVIF or WebP sibling.
     *
     * AVIF wins when both files exist. JPEG and PNG URLs stay when neither does.
     *
     * @since 0.10.0
     *
     * @see https://developer.wordpress.org/reference/hooks/wp_get_attachment_image_src/
     * @see https://developer.wordpress.org/reference/hooks/wp_calculate_image_srcset/
     * @see https://developer.wordpress.org/reference/hooks/wp_content_img_tag/
     */
    public function preferModernImage(): void
    {
        if (!$this->once('preferModernImage')) {
            return;
        }

        add_filter('wp_get_attachment_image_src', [$this, 'preferModernImageSrc']);
        add_filter('wp_calculate_image_srcset', [$this, 'preferModernImageSources'], 10, 1);
        add_filter('wp_content_img_tag', [$this, 'preferModernImageTag'], 9);
    }

    /**
     * Swaps one attachment image URL for its modern sibling.
     *
     * @since 0.10.0
     *
     * @param mixed $image Image data `[url, width, height, is_intermediate]`, or false.
     *
     * @return mixed
     */
    public function preferModernImageSrc(mixed $image): mixed
    {
        if (!is_array($image) || !isset($image[0]) || !is_string($image[0])) {
            return $image;
        }

        $image[0] = $this->modernUploadUrl($image[0]);

        return $image;
    }

    /**
     * Swaps each srcset URL for its modern sibling.
     *
     * @since 0.10.0
     *
     * @param array<int, array<string, mixed>> $sources Sources keyed by width.
     *
     * @return array<int, array<string, mixed>>
     */
    public function preferModernImageSources(array $sources): array
    {
        foreach ($sources as $width => $source) {
            if (isset($source['url']) && is_string($source['url'])) {
                $sources[$width]['url'] = $this->modernUploadUrl($source['url']);
            }
        }

        return $sources;
    }

    /**
     * Swaps `src` and `srcset` in a content image for modern siblings.
     *
     * @since 0.10.0
     *
     * @param string $image Image tag.
     *
     * @return string
     */
    public function preferModernImageTag(string $image): string
    {
        if (!class_exists(\WP_HTML_Tag_Processor::class)) {
            return $image;
        }

        $processor = new \WP_HTML_Tag_Processor($image);

        if (!$processor->next_tag('IMG')) {
            return $image;
        }

        $src = $processor->get_attribute('src');

        if (is_string($src) && $src !== '') {
            $processor->set_attribute('src', $this->modernUploadUrl($src));
        }

        $srcset = $processor->get_attribute('srcset');

        if (is_string($srcset) && $srcset !== '') {
            $processor->set_attribute('srcset', $this->modernSrcset($srcset));
        }

        return $processor->get_updated_html();
    }

    /**
     * Fills a missing `sizes` attribute from the image width.
     *
     * An existing `sizes` value stays. The formula matches WordPress:
     * `(max-width: {width}px) 100vw, {width}px`.
     *
     * @since 0.10.0
     *
     * @see https://developer.wordpress.org/reference/hooks/wp_get_attachment_image_attributes/
     * @see https://developer.wordpress.org/reference/hooks/wp_content_img_tag/
     */
    public function responsiveImageSizes(): void
    {
        if (!$this->once('responsiveImageSizes')) {
            return;
        }

        add_filter('wp_get_attachment_image_attributes', [$this, 'fillImageSizes'], 12);
        add_filter('wp_content_img_tag', [$this, 'fillImageTagSizes'], 12);
    }

    /**
     * Adds `sizes` when an attachment image has a width and no `sizes`.
     *
     * @since 0.10.0
     *
     * @param array<string, string> $attributes Image attributes.
     *
     * @return array<string, string>
     */
    public function fillImageSizes(array $attributes): array
    {
        if (isset($attributes['sizes']) || !isset($attributes['width'])) {
            return $attributes;
        }

        $width = (int) $attributes['width'];

        if ($width < 1) {
            return $attributes;
        }

        $attributes['sizes'] = sprintf('(max-width: %1$dpx) 100vw, %1$dpx', $width);

        return $attributes;
    }

    /**
     * Adds `sizes` when a content image has a width and no `sizes`.
     *
     * @since 0.10.0
     *
     * @param string $image Image tag.
     *
     * @return string
     */
    public function fillImageTagSizes(string $image): string
    {
        if (!class_exists(\WP_HTML_Tag_Processor::class)) {
            return $image;
        }

        $processor = new \WP_HTML_Tag_Processor($image);

        if (!$processor->next_tag('IMG') || $processor->get_attribute('sizes') !== null) {
            return $image;
        }

        $width = (int) $processor->get_attribute('width');

        if ($width < 1) {
            return $image;
        }

        $processor->set_attribute('sizes', sprintf('(max-width: %1$dpx) 100vw, %1$dpx', $width));

        return $processor->get_updated_html();
    }

    /**
     * Preloads the featured image and keeps it out of lazy loading.
     *
     * On a singular view the queried post thumbnail gets `fetchpriority="high"`.
     * `$size` is the size used in the preload tag. An empty size uses `large`.
     *
     * @since 0.10.0
     *
     * @param string $size Attachment image size.
     *
     * @see https://developer.wordpress.org/reference/hooks/wp_get_attachment_image_attributes/
     */
    public function prioritizeLcpImage(string $size = 'large'): void
    {
        $this->lcpImageSize = $size !== '' ? $size : 'large';

        if ($this->once('prioritizeLcpImage')) {
            add_filter('wp_get_attachment_image_attributes', [$this, 'prioritizeFeaturedImage'], 9, 2);
            add_action('wp_head', [$this, 'preloadFeaturedImage'], 2);
        }
    }

    /**
     * Marks the queried post thumbnail as the high-priority image.
     *
     * @since 0.10.0
     *
     * @param array<string, string> $attributes Image attributes.
     * @param mixed                 $attachment Attachment post.
     *
     * @return array<string, string>
     */
    public function prioritizeFeaturedImage(array $attributes, mixed $attachment): array
    {
        if (!$this->isQueriedFeaturedImage($attachment)) {
            return $attributes;
        }

        unset($attributes['loading']);
        $attributes['fetchpriority'] = 'high';
        $attributes['decoding'] = 'sync';

        return $attributes;
    }

    /**
     * Prints a preload tag for the queried post thumbnail.
     *
     * @since 0.10.0
     */
    public function preloadFeaturedImage(): void
    {
        if (
            !function_exists('is_singular')
            || !is_singular()
            || !function_exists('get_queried_object_id')
            || !function_exists('get_post_thumbnail_id')
        ) {
            return;
        }

        $attachmentId = (int) get_post_thumbnail_id(get_queried_object_id());

        if ($attachmentId < 1 || !function_exists('wp_get_attachment_image_src')) {
            return;
        }

        $image = wp_get_attachment_image_src($attachmentId, $this->lcpImageSize);

        if (!is_array($image) || !isset($image[0]) || !is_string($image[0]) || $image[0] === '') {
            return;
        }

        echo '<link rel="preload" as="image" href="' . esc_url($image[0]) . '" fetchpriority="high"';

        $srcset = function_exists('wp_get_attachment_image_srcset')
            ? wp_get_attachment_image_srcset($attachmentId, $this->lcpImageSize)
            : '';

        if (is_string($srcset) && $srcset !== '') {
            echo ' imagesrcset="' . esc_attr($srcset) . '"';
        }

        $sizes = function_exists('wp_get_attachment_image_sizes')
            ? wp_get_attachment_image_sizes($attachmentId, $this->lcpImageSize)
            : '';

        if (is_string($sizes) && $sizes !== '') {
            echo ' imagesizes="' . esc_attr($sizes) . '"';
        }

        echo ">\n";
    }

    /**
     * Adds lazy loading and async decoding when WordPress left them off.
     *
     * Images with `fetchpriority="high"` are unchanged. Lazy loading is added
     * only when the tag already has width and height.
     *
     * @since 0.10.0
     *
     * @see https://developer.wordpress.org/reference/hooks/wp_get_attachment_image_attributes/
     * @see https://developer.wordpress.org/reference/hooks/wp_content_img_tag/
     */
    public function lazyLoadImages(): void
    {
        if (!$this->once('lazyLoadImages')) {
            return;
        }

        add_filter('wp_get_attachment_image_attributes', [$this, 'optimizeAttachmentAttributes']);
        add_filter('wp_content_img_tag', [$this, 'optimizeImageTag']);
    }

    /**
     * Adds the dashboard box that regenerates existing images.
     *
     * @since 0.10.0
     *
     * @see https://developer.wordpress.org/reference/functions/wp_add_dashboard_widget/
     */
    public function registerImageDashboard(): void
    {
        if (!$this->once('registerImageDashboard')) {
            return;
        }

        add_action('wp_dashboard_setup', [$this, 'addImageDashboard']);
        add_action('admin_enqueue_scripts', [$this, 'enqueueImageDashboard']);
        add_action('wp_ajax_wpb_regenerate_images', [$this, 'regenerateImages']);
    }

    /**
     * Adds the image box for administrators.
     *
     * @since 0.10.0
     */
    public function addImageDashboard(): void
    {
        if (!current_user_can('manage_options')) {
            return;
        }

        wp_add_dashboard_widget(
            'wpb_images',
            __('Image files', 'wpb'),
            [$this, 'renderImageDashboard']
        );
    }

    /**
     * Prints the dashboard image box.
     *
     * @since 0.10.0
     */
    public function renderImageDashboard(): void
    {
        include Builder::PATH['PAGE'] . 'performance/images.php';
    }

    /**
     * Loads the regenerate script on the dashboard.
     *
     * @since 0.10.0
     *
     * @param string $hook Current admin page hook.
     */
    public function enqueueImageDashboard(string $hook): void
    {
        if ($hook !== 'index.php' || !current_user_can('manage_options')) {
            return;
        }

        $path = dirname(__DIR__, 2) . '/assets/js/admin-regenerate-images.js';

        if (!is_readable($path)) {
            return;
        }

        $url = Builder::url($path);

        if ($url === '') {
            return;
        }

        wp_enqueue_script(
            'wpb-regenerate-images',
            $url,
            [],
            (string) filemtime($path),
            true
        );
    }

    /**
     * Regenerates one batch of existing images and returns JSON.
     *
     * @since 0.10.0
     */
    public function regenerateImages(): void
    {
        if (!current_user_can('manage_options')) {
            wp_send_json_error([
                'message' => __('Sorry, you are not allowed to do that.', 'wpb'),
            ], 403);
        }

        check_ajax_referer('wpb_regenerate_images', 'nonce');

        $offset = max(0, (int) wp_unslash($_POST['offset'] ?? 0));
        $failed = max(0, (int) wp_unslash($_POST['failed'] ?? 0));

        wp_send_json_success($this->regenerateImageBatch($offset, $failed));
    }

    /**
     * Regenerates up to three images, always at least one when any remain.
     *
     * @since 0.10.0
     *
     * @param int $offset Images already handled in this run.
     * @param int $failed Images that failed earlier in this run.
     *
     * @return array{offset: int, total: int, done: bool, failed: int, message: string}
     */
    public function regenerateImageBatch(int $offset, int $failed = 0): array
    {
        $total = $this->imageAttachmentCount();
        $started = microtime(true);
        $processed = 0;

        do {
            $ids = $this->imageAttachmentIds($offset + $processed, 1);

            if ($ids === []) {
                break;
            }

            if (!$this->regenerateAttachment($ids[0])) {
                $failed++;
            }

            $processed++;
        } while ($processed < 3 && (microtime(true) - $started) < 10);

        $next = $offset + $processed;
        $done = $next >= $total || $processed === 0;

        return [
            'offset' => $next,
            'total' => $total,
            'done' => $done,
            'failed' => $failed,
            'message' => $this->regenerateMessage($next, $total, $failed, $done),
        ];
    }

    /**
     * Counts JPEG, PNG, WebP, and AVIF attachments.
     *
     * @since 0.10.0
     *
     * @return int
     */
    public function imageAttachmentCount(): int
    {
        if (!function_exists('wp_count_attachments')) {
            return 0;
        }

        $total = 0;

        foreach ((array) wp_count_attachments() as $mime => $count) {
            if (in_array($mime, self::REGENERATED_IMAGE_TYPES, true)) {
                $total += (int) $count;
            }
        }

        return $total;
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
        $target = $this->modernImageMime();

        if ($target === null) {
            return $formats;
        }

        $formats['image/jpeg'] = $target;
        $formats['image/png'] = $target;

        return $formats;
    }

    /**
     * Writes a full-size AVIF or WebP next to a JPEG or PNG original.
     *
     * Scaled uploads are already stored in the modern format, so this returns
     * the metadata unchanged when the attached file is AVIF or WebP. An existing
     * sibling is left in place.
     *
     * @since 0.10.0
     *
     * @param array<string, mixed> $metadata     Attachment metadata.
     * @param int                  $attachmentId Attachment ID.
     *
     * @return array<string, mixed>
     */
    public function addModernFullImage(array $metadata, int $attachmentId): array
    {
        if (!function_exists('get_attached_file') || !function_exists('wp_get_image_editor')) {
            return $metadata;
        }

        $file = get_attached_file($attachmentId);

        if (!is_string($file) || !is_file($file)) {
            return $metadata;
        }

        $mime = function_exists('wp_get_image_mime') ? wp_get_image_mime($file) : null;

        if ($mime !== 'image/jpeg' && $mime !== 'image/png') {
            return $metadata;
        }

        $target = $this->modernImageMime();

        if ($target === null) {
            return $metadata;
        }

        $destination = $this->replaceExtension($file, $target === 'image/avif' ? 'avif' : 'webp');

        if ($destination === $file || is_file($destination)) {
            return $metadata;
        }

        $editor = wp_get_image_editor($file);

        if (is_wp_error($editor)) {
            return $metadata;
        }

        $editor->save($destination, $target);

        return $metadata;
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
     * Enables every performance feature with its default argument.
     *
     * Heartbeat, revisions, trash, and the page cache keep a value already set
     * on this instance. The defaults are 60 seconds, 5 revisions, 7 days, and
     * a cache lifetime of 3600 seconds. Image defaults stay inside `images()`.
     *
     * @since 0.10.0
     */
    public function enableAll(): void
    {
        $this->disableEmoji();
        $this->cleanHead();
        $this->disableEmbeds();

        if (!isset($this->active['heartbeat'])) {
            $this->heartbeat();
        }

        if (!isset($this->active['revisions'])) {
            $this->revisions();
        }

        if (!isset($this->active['emptyTrash'])) {
            $this->emptyTrash();
        }

        $this->images();

        if (!$this->cache instanceof PageCache) {
            $this->enableCache();
        }
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
     * Returns a page of image attachment IDs.
     *
     * @since 0.10.0
     *
     * @param int $offset Number of images to skip.
     * @param int $limit  Maximum IDs to return.
     *
     * @return list<int>
     */
    private function imageAttachmentIds(int $offset, int $limit): array
    {
        if (!function_exists('get_posts') || $limit < 1) {
            return [];
        }

        $ids = get_posts([
            'post_type' => 'attachment',
            'post_status' => 'inherit',
            'post_mime_type' => self::REGENERATED_IMAGE_TYPES,
            'posts_per_page' => $limit,
            'offset' => max(0, $offset),
            'fields' => 'ids',
            'orderby' => 'ID',
            'order' => 'ASC',
            'suppress_filters' => true,
        ]);

        return array_map(static fn (mixed $id): int => (int) $id, (array) $ids);
    }

    /**
     * Rebuilds one attachment from its original file.
     *
     * Old generated files that the new metadata does not use are removed.
     * The original file stays.
     *
     * @since 0.10.0
     *
     * @param int $attachmentId Attachment ID.
     *
     * @return bool False when the file is missing or the editor cannot read it.
     */
    private function regenerateAttachment(int $attachmentId): bool
    {
        if (!function_exists('wp_generate_attachment_metadata')) {
            require_once ABSPATH . 'wp-admin/includes/image.php';
        }

        $original = $this->originalImagePath($attachmentId);

        if ($original === null) {
            return false;
        }

        $previous = $this->generatedImageFiles($attachmentId, $original);
        $metadata = wp_generate_attachment_metadata($attachmentId, $original);

        if (!is_array($metadata) || !isset($metadata['width'])) {
            return false;
        }

        wp_update_attachment_metadata($attachmentId, $metadata);
        $this->deleteStaleImages($previous, $this->generatedImageFiles($attachmentId, $original), $original);

        return true;
    }

    /**
     * Builds the status line for a regenerate batch.
     *
     * @since 0.10.0
     *
     * @param int  $done   Images handled so far.
     * @param int  $total  Images in the library.
     * @param int  $failed Images that could not be rebuilt.
     * @param bool $finished Whether the run has reached the end.
     *
     * @return string
     */
    private function regenerateMessage(int $done, int $total, int $failed, bool $finished): string
    {
        if (!$finished) {
            return sprintf(
                /* translators: 1: images regenerated so far, 2: total images */
                __('%1$d of %2$d images regenerated.', 'wpb'),
                $done,
                $total
            );
        }

        if ($failed > 0) {
            return sprintf(
                /* translators: 1: images regenerated, 2: images that failed */
                __('Finished. Regenerated %1$d images. %2$d failed.', 'wpb'),
                max(0, $done - $failed),
                $failed
            );
        }

        return sprintf(
            /* translators: %d: number of images regenerated */
            __('Finished. Regenerated %d images.', 'wpb'),
            $done
        );
    }

    /**
     * Resolves the original upload for an attachment.
     *
     * @since 0.10.0
     *
     * @param int $attachmentId Attachment ID.
     *
     * @return string|null Absolute path, or null when the file is missing.
     */
    private function originalImagePath(int $attachmentId): ?string
    {
        $path = function_exists('wp_get_original_image_path')
            ? wp_get_original_image_path($attachmentId)
            : false;

        if (!is_string($path) || !is_file($path)) {
            $path = function_exists('get_attached_file') ? get_attached_file($attachmentId) : false;
        }

        if (!is_string($path) || !is_file($path)) {
            return null;
        }

        return $path;
    }

    /**
     * Lists generated files for an attachment, excluding the original.
     *
     * @since 0.10.0
     *
     * @param int    $attachmentId Attachment ID.
     * @param string $original     Absolute path of the original file.
     *
     * @return list<string>
     */
    private function generatedImageFiles(int $attachmentId, string $original): array
    {
        $files = [];
        $metadata = function_exists('wp_get_attachment_metadata')
            ? wp_get_attachment_metadata($attachmentId)
            : null;
        $directory = dirname($original);

        if (is_array($metadata)) {
            foreach ($metadata['sizes'] ?? [] as $size) {
                if (!is_array($size)) {
                    continue;
                }

                if (isset($size['file']) && is_string($size['file']) && $size['file'] !== '') {
                    $files[] = $directory . '/' . basename($size['file']);
                }

                foreach ($size['sources'] ?? [] as $source) {
                    if (
                        is_array($source)
                        && isset($source['file'])
                        && is_string($source['file'])
                        && $source['file'] !== ''
                    ) {
                        $files[] = $directory . '/' . basename($source['file']);
                    }
                }
            }
        }

        $attached = function_exists('get_attached_file') ? get_attached_file($attachmentId) : false;

        if (is_string($attached) && $attached !== '') {
            $files[] = $attached;
            $files = array_merge($files, $this->modernSiblings($attached));
        }

        return array_merge($files, $this->modernSiblings($original));
    }

    /**
     * Returns AVIF and WebP paths beside an image.
     *
     * @since 0.10.0
     *
     * @param string $path Absolute path.
     *
     * @return list<string>
     */
    private function modernSiblings(string $path): array
    {
        $siblings = [];

        foreach (['avif', 'webp'] as $extension) {
            $sibling = $this->replaceExtension($path, $extension);

            if ($sibling !== $path) {
                $siblings[] = $sibling;
            }
        }

        return $siblings;
    }

    /**
     * Deletes generated files that the new metadata no longer uses.
     *
     * @since 0.10.0
     *
     * @param list<string> $previous Paths collected before the rebuild.
     * @param list<string> $current  Paths collected after the rebuild.
     * @param string       $original Absolute path of the original file.
     */
    private function deleteStaleImages(array $previous, array $current, string $original): void
    {
        if (!function_exists('wp_get_upload_dir')) {
            return;
        }

        $uploads = wp_get_upload_dir();
        $base = realpath((string) ($uploads['basedir'] ?? ''));
        $originalReal = realpath($original);

        if ($base === false || $originalReal === false) {
            return;
        }

        $keep = [$originalReal => true];

        foreach ($current as $file) {
            $real = realpath($file);

            if (is_string($real)) {
                $keep[$real] = true;
            }
        }

        foreach ($previous as $file) {
            $real = realpath($file);

            if ($real === false || isset($keep[$real]) || !is_file($real)) {
                continue;
            }

            if (!str_starts_with($real, $base . DIRECTORY_SEPARATOR)) {
                continue;
            }

            unlink($real);
        }
    }

    /**
     * Strips one image file, rotating JPEG and converting it to sRGB first.
     *
     * @since 0.10.0
     *
     * @param string $file Absolute path.
     * @param string $mime Mime type.
     */
    private function stripImageFile(string $file, string $mime): void
    {
        if (class_exists(\Imagick::class) && $this->stripWithImagick($file, $mime)) {
            return;
        }

        if ($mime === 'image/jpeg' || $mime === 'image/png') {
            $this->stripWithGd($file, $mime);
        }
    }

    /**
     * Builds a temporary path that keeps the original image extension.
     *
     * Imagick chooses the output format from the extension.
     *
     * @since 0.10.0
     *
     * @param string $file Absolute path.
     *
     * @return string
     */
    private function temporaryImagePath(string $file): string
    {
        $extension = pathinfo($file, PATHINFO_EXTENSION);
        $suffix = $extension === '' ? '.wpb-tmp' : '.wpb-tmp.' . $extension;

        return $file . $suffix;
    }

    /**
     * Rewrites one image in place with Imagick.
     *
     * @since 0.10.0
     *
     * @param string $file Absolute path.
     * @param string $mime Mime type.
     *
     * @return bool True when the file was replaced.
     */
    private function stripWithImagick(string $file, string $mime): bool
    {
        try {
            $image = new \Imagick($file);
        } catch (\Throwable) {
            return false;
        }

        $temporary = $this->temporaryImagePath($file);

        try {
            $quality = $image->getImageCompressionQuality();

            if ($mime === 'image/jpeg' && method_exists($image, 'autoOrient')) {
                $image->autoOrient();
            }

            if ($mime === 'image/jpeg' && defined('Imagick::COLORSPACE_SRGB')) {
                $image->transformImageColorspace(\Imagick::COLORSPACE_SRGB);
            }

            $image->stripImage();

            if ($quality > 0) {
                $image->setImageCompressionQuality($quality);
            }

            $written = $image->writeImage($temporary);
            $image->clear();
        } catch (\Throwable) {
            $image->clear();

            if (is_file($temporary)) {
                unlink($temporary);
            }

            return false;
        }

        if ($written !== true || !is_file($temporary)) {
            if (is_file($temporary)) {
                unlink($temporary);
            }

            return false;
        }

        if (!rename($temporary, $file)) {
            unlink($temporary);

            return false;
        }

        return true;
    }

    /**
     * Rewrites a JPEG or PNG with GD, which drops embedded metadata.
     *
     * @since 0.10.0
     *
     * @param string $file Absolute path.
     * @param string $mime Mime type.
     */
    private function stripWithGd(string $file, string $mime): void
    {
        $image = $this->gdImage($file, $mime);

        if (!$image instanceof \GdImage) {
            return;
        }

        if ($mime === 'image/jpeg') {
            $image = $this->rotateGdJpeg($image, $file);
        }

        if ($mime === 'image/png') {
            imagealphablending($image, false);
            imagesavealpha($image, true);
        }

        $temporary = $this->temporaryImagePath($file);
        $saved = $mime === 'image/png'
            ? imagepng($image, $temporary, 9)
            : imagejpeg($image, $temporary, 90);
        unset($image);

        if ($saved !== true || !is_file($temporary)) {
            if (is_file($temporary)) {
                unlink($temporary);
            }

            return;
        }

        if (!rename($temporary, $file)) {
            unlink($temporary);
        }
    }

    /**
     * Loads a JPEG or PNG into GD.
     *
     * @since 0.10.0
     *
     * @param string $file Absolute path.
     * @param string $mime Mime type.
     *
     * @return \GdImage|false
     */
    private function gdImage(string $file, string $mime): \GdImage|false
    {
        if ($mime === 'image/jpeg' && function_exists('imagecreatefromjpeg')) {
            return imagecreatefromjpeg($file);
        }

        if ($mime === 'image/png' && function_exists('imagecreatefrompng')) {
            return imagecreatefrompng($file);
        }

        return false;
    }

    /**
     * Rotates a JPEG resource from its EXIF orientation.
     *
     * @since 0.10.0
     *
     * @param \GdImage $image GD image.
     * @param string   $file  Absolute path used to read EXIF.
     *
     * @return \GdImage
     */
    private function rotateGdJpeg(\GdImage $image, string $file): \GdImage
    {
        $angle = match ((int) ($this->readExif($file)['Orientation'] ?? 0)) {
            3 => 180,
            6 => -90,
            8 => 90,
            default => 0,
        };

        if ($angle === 0) {
            return $image;
        }

        $rotated = imagerotate($image, $angle, 0);

        if (!$rotated instanceof \GdImage) {
            return $image;
        }

        unset($image);

        return $rotated;
    }

    /**
     * Reads EXIF data, ignoring warnings from malformed files.
     *
     * @since 0.10.0
     *
     * @param string $file Absolute path.
     *
     * @return array<string, mixed>
     */
    private function readExif(string $file): array
    {
        if (!function_exists('exif_read_data')) {
            return [];
        }

        set_error_handler(static fn (): bool => true);

        try {
            $exif = exif_read_data($file);
        } finally {
            restore_error_handler();
        }

        return is_array($exif) ? $exif : [];
    }

    /**
     * Returns AVIF when the editor supports it, otherwise WebP.
     *
     * @since 0.10.0
     *
     * @return string|null Null when neither format can be saved.
     */
    private function modernImageMime(): ?string
    {
        if (!function_exists('wp_image_editor_supports')) {
            return null;
        }

        if (wp_image_editor_supports(['mime_type' => 'image/avif'])) {
            return 'image/avif';
        }

        if (wp_image_editor_supports(['mime_type' => 'image/webp'])) {
            return 'image/webp';
        }

        return null;
    }

    /**
     * Replaces the extension of a filesystem path.
     *
     * @since 0.10.0
     *
     * @param string $path      Absolute path.
     * @param string $extension New extension without the dot.
     *
     * @return string
     */
    private function replaceExtension(string $path, string $extension): string
    {
        $directory = pathinfo($path, PATHINFO_DIRNAME);
        $filename = pathinfo($path, PATHINFO_FILENAME);

        if ($directory === '' || $filename === '') {
            return $path;
        }

        return $directory . '/' . $filename . '.' . $extension;
    }

    /**
     * Returns an upload URL, swapping JPEG or PNG for an existing modern file.
     *
     * @since 0.10.0
     *
     * @param string $url Attachment URL.
     *
     * @return string
     */
    private function modernUploadUrl(string $url): string
    {
        $query = '';
        $bare = $url;
        $queryAt = strpos($url, '?');

        if ($queryAt !== false) {
            $bare = substr($url, 0, $queryAt);
            $query = substr($url, $queryAt);
        }

        $path = $this->uploadFileFromUrl($bare);

        if ($path === null) {
            return $url;
        }

        $extension = strtolower(pathinfo($path, PATHINFO_EXTENSION));

        if (!in_array($extension, ['jpg', 'jpeg', 'png'], true)) {
            return $url;
        }

        $prefix = substr($path, 0, -strlen($extension));

        foreach (['avif', 'webp'] as $candidate) {
            if (is_file($prefix . $candidate)) {
                return substr($bare, 0, -strlen($extension)) . $candidate . $query;
            }
        }

        return $url;
    }

    /**
     * Rewrites each URL in a srcset attribute.
     *
     * @since 0.10.0
     *
     * @param string $srcset Srcset attribute value.
     *
     * @return string
     */
    private function modernSrcset(string $srcset): string
    {
        $updated = [];

        foreach (array_map('trim', explode(',', $srcset)) as $part) {
            if ($part === '') {
                continue;
            }

            $pieces = preg_split('/\s+/', $part, 2);

            if ($pieces === false || !isset($pieces[0])) {
                continue;
            }

            $url = $this->modernUploadUrl($pieces[0]);
            $updated[] = isset($pieces[1]) ? $url . ' ' . $pieces[1] : $url;
        }

        return implode(', ', $updated);
    }

    /**
     * Resolves an upload URL to a real file inside the uploads directory.
     *
     * @since 0.10.0
     *
     * @param string $url Upload URL without a query string.
     *
     * @return string|null Absolute path, or null when the URL is outside uploads.
     */
    private function uploadFileFromUrl(string $url): ?string
    {
        if (!function_exists('wp_get_upload_dir')) {
            return null;
        }

        $uploads = wp_get_upload_dir();
        $baseurl = (string) ($uploads['baseurl'] ?? '');
        $basedir = (string) ($uploads['basedir'] ?? '');

        if ($baseurl === '' || $basedir === '' || !str_starts_with($url, $baseurl . '/')) {
            return null;
        }

        $relative = rawurldecode(substr($url, strlen($baseurl)));

        if (str_contains($relative, '..')) {
            return null;
        }

        $realBase = realpath($basedir);
        $realPath = realpath($basedir . $relative);

        if (
            $realBase === false
            || $realPath === false
            || !str_starts_with($realPath, $realBase . DIRECTORY_SEPARATOR)
        ) {
            return null;
        }

        return $realPath;
    }

    /**
     * Reports whether an attachment is the thumbnail of the queried singular post.
     *
     * @since 0.10.0
     *
     * @param mixed $attachment Attachment passed by WordPress.
     *
     * @return bool
     */
    private function isQueriedFeaturedImage(mixed $attachment): bool
    {
        if (
            !$attachment instanceof \WP_Post
            || !function_exists('is_singular')
            || !is_singular()
            || !function_exists('get_queried_object_id')
            || !function_exists('get_post_thumbnail_id')
        ) {
            return false;
        }

        return (int) $attachment->ID === (int) get_post_thumbnail_id(get_queried_object_id());
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
            'limitImageDimension' => $this->limitImageDimension(is_int($argument) ? $argument : 2048),
            'stripImageMetadata' => $this->stripImageMetadata(),
            'modernImageFormat' => $this->modernImageFormat(),
            'imageQuality' => $this->imageQuality(is_int($argument) ? $argument : 75),
            'disableExtraImageSizes' => $this->disableExtraImageSizes(),
            'capImageDensity' => $this->capImageDensity(),
            'preferModernImage' => $this->preferModernImage(),
            'responsiveImageSizes' => $this->responsiveImageSizes(),
            'prioritizeLcpImage' => $this->prioritizeLcpImage(is_string($argument) ? $argument : 'large'),
            'lazyLoadImages' => $this->lazyLoadImages(),
            'images' => $this->images(),
            'enableCache' => $this->enableCache(is_int($argument) ? $argument : 3600),
            'enableAll' => $this->enableAll(),
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
