<?php

/**
 * Anonymous HTML page cache.
 *
 * @author  Renato Rodrigues Jr <juniorenato@msn.com>
 * @license GPL-3.0-or-later
 * @package WPB\Performance
 */

namespace WPB\Performance;

use WPB\MetaBoxes\MetaBox;

/**
 * Stores rendered HTML and clears it when the underlying content changes.
 *
 * `Performance::enableCache()` is what registers this class. Until that call,
 * nothing is stored or served. A hit still boots WordPress. At
 * `template_redirect` a fresh file is sent and the template does not render
 * again. The HTML file name is the MD5 of the URL. The Tools → Cache screen
 * is an `AdminPage`: its fields are options, and the URL table clears files.
 * Each page has a sidebar checkbox that keeps that page out of the cache.
 *
 * @since  0.10.0
 * @author Renato Rodrigues Jr <juniorenato@msn.com>
 */
class PageCache
{
    /**
     * Option that turns serving and writing on or off.
     *
     * @since 0.10.0
     */
    public const string OPTION_ENABLED = 'wpb_page_cache_enabled';

    /**
     * Prefix for the option that stores pages linked to one post type.
     *
     * @since 0.10.0
     */
    public const string OPTION_PAGES_PREFIX = 'wpb_page_cache_pages_';

    /**
     * Post meta that keeps one page out of the cache.
     *
     * @since 0.10.0
     */
    public const string META_DISABLED = 'wpb_page_cache_disabled';

    /**
     * Tools submenu slug.
     *
     * @since 0.10.0
     */
    public const string MENU_SLUG = 'wpb-page-cache';

    /**
     * How long a stored file stays valid, in seconds.
     *
     * @since 0.10.0
     */
    private int $ttl;

    /**
     * Whether this request is capturing the template output.
     *
     * @since 0.10.0
     */
    private bool $capturing = false;

    /**
     * Prepares the cache lifetime.
     *
     * @since 0.10.0
     *
     * @param int $ttl Cache lifetime in seconds.
     */
    public function __construct(int $ttl = 3600)
    {
        $this->ttl = $ttl >= 1 ? $ttl : 3600;
    }

    /**
     * Changes the lifetime used for later reads.
     *
     * @since 0.10.0
     *
     * @param int $seconds Cache lifetime in seconds.
     */
    public function ttl(int $seconds): void
    {
        if ($seconds >= 1) {
            $this->ttl = $seconds;
        }
    }

    /**
     * Tools screen registered for this cache.
     *
     * @since 0.10.0
     */
    private ?CacheScreen $screen = null;

    /**
     * Page editor meta box registered for this cache.
     *
     * @since 0.10.0
     */
    private ?MetaBox $metaBox = null;

    /**
     * Hooks serving, storage, invalidation, and the Tools screen.
     *
     * @since 0.10.0
     */
    public function register(): void
    {
        wp_mkdir_p($this->directory());

        add_action('template_redirect', [$this, 'serve'], 0);
        add_action('shutdown', [$this, 'store'], 0);
        add_action('save_post', [$this, 'purgePost'], 20, 2);
        add_action('before_delete_post', [$this, 'purgePost'], 20, 2);
        add_action('post_updated', [$this, 'purgeRenamed'], 20, 3);
        add_action('admin_init', [$this, 'handlePost']);

        if (is_admin()) {
            if (did_action('init')) {
                $this->registerScreen();
                $this->registerMetaBox();
            } else {
                add_action('init', [$this, 'registerScreen'], 20);
                add_action('init', [$this, 'registerMetaBox'], 20);
            }
        }

        if (get_option(self::OPTION_ENABLED, null) === null) {
            add_option(self::OPTION_ENABLED, '1', '', true);
        }
    }

    /**
     * Registers the Tools screen after public post types exist.
     *
     * @since 0.10.0
     */
    public function registerScreen(): void
    {
        if ($this->screen instanceof CacheScreen) {
            return;
        }

        $screen = new CacheScreen($this);
        $screen->title(__('Cache', 'wpb'));
        $screen->slug(self::MENU_SLUG);
        $screen->parent('tools');
        $screen->addCheckboxField(
            self::OPTION_ENABLED,
            __('Status', 'wpb'),
            ['1' => __('Enable page cache', 'wpb')],
            false
        );

        foreach ($this->postTypeChoices() as $slug => $label) {
            $screen->addSelectField(
                self::pagesOption($slug),
                $label,
                $this->pageChoices(),
                true,
                '',
                '',
                ['size' => 6]
            );
        }

        $this->screen = $screen;
        $screen->register();
    }

    /**
     * Option name for the pages linked to a post type.
     *
     * @since 0.10.0
     *
     * @param string $postType Post type slug.
     */
    public static function pagesOption(string $postType): string
    {
        return self::OPTION_PAGES_PREFIX . sanitize_key($postType);
    }

    /**
     * Registers the sidebar checkbox on every page.
     *
     * @since 0.10.0
     */
    public function registerMetaBox(): void
    {
        if ($this->metaBox instanceof MetaBox) {
            return;
        }

        $box = new MetaBox();
        $box->metaBoxId('wpb-page-cache');
        $box->title(__('Cache', 'wpb'));
        $box->screen('page');
        $box->context('side');
        $box->priority('high');
        $box->addCheckboxField(
            self::META_DISABLED,
            __('Disable cache', 'wpb'),
            [],
            false,
            __('This page is rendered on every visit and is not stored.', 'wpb')
        );
        $box->register();

        add_filter('hidden_meta_boxes', [$this, 'keepMetaBoxVisible'], 10, 2);
        add_filter('default_hidden_meta_boxes', [$this, 'keepMetaBoxVisible'], 10, 2);

        $this->metaBox = $box;
    }

    /**
     * Keeps the page cache meta box visible on the page editor.
     *
     * @since 0.10.0
     *
     * @param array<int, string> $hidden Hidden meta box identifiers.
     * @param mixed              $screen Current screen.
     *
     * @return array<int, string> Hidden identifiers without this meta box.
     */
    public function keepMetaBoxVisible(array $hidden, mixed $screen): array
    {
        $screenId = is_object($screen) ? (string) ($screen->id ?? '') : (string) $screen;

        if ($screenId !== 'page') {
            return $hidden;
        }

        return array_values(array_diff($hidden, ['wpb-page-cache']));
    }

    /**
     * Reports whether the current page opted out of the cache.
     *
     * @since 0.10.0
     */
    private function pageDisablesCache(): bool
    {
        if (!is_page()) {
            return false;
        }

        $pageId = (int) get_queried_object_id();

        if ($pageId < 1) {
            return false;
        }

        return (string) get_post_meta($pageId, self::META_DISABLED, true) === '1';
    }

    /**
     * Sends a fresh cached file and stops the template.
     *
     * @since 0.10.0
     */
    public function serve(): void
    {
        if (!$this->shouldCache()) {
            return;
        }

        $url = $this->requestUrl();
        $file = $this->htmlFile($url);

        if ($url === '' || !$this->fresh($url) || !is_file($file)) {
            $this->capturing = true;
            ob_start();

            return;
        }

        header('Content-Type: text/html; charset=' . get_option('blog_charset'));
        header('X-WPB-Cache: HIT');
        readfile($file);
        exit;
    }

    /**
     * Writes the captured HTML when the response is cacheable.
     *
     * @since 0.10.0
     */
    public function store(): void
    {
        if (!$this->capturing) {
            return;
        }

        $html = (string) ob_get_contents();

        if (!$this->shouldStore($html)) {
            return;
        }

        $this->remember($this->requestUrl(), $html);
    }

    /**
     * Removes the post, its archive, and the pages linked to its post type.
     *
     * @since 0.10.0
     *
     * @param int      $postId Post ID.
     * @param \WP_Post $post   Post object.
     */
    public function purgePost(int $postId, \WP_Post $post): void
    {
        unset($postId);

        if (!$this->tracks($post)) {
            return;
        }

        $link = get_permalink($post);

        if (is_string($link)) {
            $this->forgetUrl($link);
        }

        $this->forgetArchive($post->post_type);
        $this->forgetLinked($post->post_type);
    }

    /**
     * Removes a stored URL left behind by a slug change.
     *
     * @since 0.10.0
     *
     * @param int      $postId Post ID.
     * @param \WP_Post $after  Post after the update.
     * @param \WP_Post $before Post before the update.
     */
    public function purgeRenamed(int $postId, \WP_Post $after, \WP_Post $before): void
    {
        unset($postId);

        if (!$this->tracks($after) || $before->post_name === '' || $before->post_name === $after->post_name) {
            return;
        }

        $needle = '/' . $before->post_name;

        foreach ($this->entries() as $entry) {
            $path = untrailingslashit((string) (wp_parse_url($entry['url'], PHP_URL_PATH) ?: ''));

            if ($path === $needle || str_ends_with($path, $needle)) {
                $this->deleteHash($entry['hash']);
            }
        }
    }

    /**
     * Stores one URL.
     *
     * @since 0.10.0
     *
     * @param string $url  Public URL.
     * @param string $html Rendered HTML.
     */
    public function remember(string $url, string $html): void
    {
        $url = $this->normalize($url);

        if ($url === '' || $html === '') {
            return;
        }

        $directory = $this->directory();

        if (!wp_mkdir_p($directory)) {
            return;
        }

        $hash = $this->hash($url);
        file_put_contents($directory . '/' . $hash . '.html', $html, LOCK_EX);
        file_put_contents($directory . '/' . $hash . '.json', wp_json_encode([
            'url' => $url,
            'created' => time(),
        ]), LOCK_EX);
    }

    /**
     * Deletes the file for one URL.
     *
     * @since 0.10.0
     *
     * @param string $url Public URL.
     */
    public function forgetUrl(string $url): void
    {
        $url = $this->normalize($url);

        if ($url === '') {
            return;
        }

        $this->deleteHash($this->hash($url));
    }

    /**
     * Deletes a post type archive and its paginated URLs.
     *
     * @since 0.10.0
     *
     * @param string $postType Post type slug.
     */
    public function forgetArchive(string $postType): void
    {
        $url = get_post_type_archive_link($postType);

        if (!is_string($url) || $url === '') {
            return;
        }

        $this->forgetListing($url);
    }

    /**
     * Deletes a listing URL and the `/page/N/` URLs under it.
     *
     * The site root only drops `/` and `/page/N/`. It does not drop every path.
     *
     * @since 0.10.0
     *
     * @param string $url Archive or home URL.
     */
    public function forgetListing(string $url): void
    {
        $url = $this->normalize($url);

        if ($url === '') {
            return;
        }

        $this->forgetUrl($url);

        $path = (string) (wp_parse_url($url, PHP_URL_PATH) ?: '/');
        $prefix = $path === '/' ? '/page/' : untrailingslashit($path) . '/page/';

        foreach ($this->entries() as $entry) {
            $entryPath = (string) (wp_parse_url($entry['url'], PHP_URL_PATH) ?: '/');

            if (!str_starts_with($entryPath, $prefix)) {
                continue;
            }

            $rest = substr($entryPath, strlen($prefix));

            if (preg_match('#^[0-9]+/?$#', $rest) === 1) {
                $this->deleteHash($entry['hash']);
            }
        }
    }

    /**
     * Deletes pages linked to a post type on the Tools screen.
     *
     * @since 0.10.0
     *
     * @param string $postType Post type slug.
     */
    public function forgetLinked(string $postType): void
    {
        foreach ($this->linked($postType) as $pageId) {
            $link = get_permalink($pageId);

            if (is_string($link)) {
                $this->forgetUrl($link);
            }
        }
    }

    /**
     * Deletes every cached file.
     *
     * @since 0.10.0
     */
    public function flush(): void
    {
        foreach (['html', 'json'] as $extension) {
            foreach (glob($this->directory() . '/*.' . $extension) ?: [] as $file) {
                if (is_file($file)) {
                    unlink($file);
                }
            }
        }
    }

    /**
     * Deletes one cached file by its hash.
     *
     * @since 0.10.0
     *
     * @param string $hash MD5 file name without an extension.
     */
    public function forget(string $hash): void
    {
        if (preg_match('/^[a-f0-9]{32}$/', $hash) === 1) {
            $this->deleteHash($hash);
        }
    }

    /**
     * Lists the cached URLs.
     *
     * @since 0.10.0
     *
     * @return list<array{url: string, created: int, hash: string}>
     */
    public function entries(): array
    {
        $entries = [];

        foreach (glob($this->directory() . '/*.json') ?: [] as $metaFile) {
            $meta = json_decode((string) file_get_contents($metaFile), true);
            $hash = basename($metaFile, '.json');

            if (!is_array($meta) || empty($meta['url']) || !is_file($this->directory() . '/' . $hash . '.html')) {
                continue;
            }

            $entries[] = [
                'url' => (string) $meta['url'],
                'created' => (int) ($meta['created'] ?? filemtime($metaFile)),
                'hash' => $hash,
            ];
        }

        usort($entries, static fn (array $left, array $right): int => strcmp($left['url'], $right['url']));

        return $entries;
    }

    /**
     * Reports whether a stored URL is inside the lifetime.
     *
     * @since 0.10.0
     *
     * @param string $url Public URL.
     *
     * @return bool True when the file exists and is younger than the lifetime.
     */
    public function fresh(string $url): bool
    {
        $url = $this->normalize($url);
        $metaFile = $this->directory() . '/' . $this->hash($url) . '.json';

        if ($url === '' || !is_file($metaFile)) {
            return false;
        }

        $meta = json_decode((string) file_get_contents($metaFile), true);
        $created = is_array($meta) ? (int) ($meta['created'] ?? 0) : 0;

        return $created > 0 && (time() - $created) < $this->ttl;
    }

    /**
     * Normalizes a public URL to scheme, host, and path.
     *
     * @since 0.10.0
     *
     * @param string $url Raw URL.
     *
     * @return string Empty when the URL has no host.
     */
    public function normalize(string $url): string
    {
        $parts = wp_parse_url($url);

        if (!is_array($parts) || empty($parts['host'])) {
            return '';
        }

        $scheme = strtolower((string) ($parts['scheme'] ?? 'https'));
        $host = strtolower((string) $parts['host']);
        $path = isset($parts['path']) ? '/' . ltrim((string) $parts['path'], '/') : '/';

        if ($path !== '/') {
            $path = user_trailingslashit(untrailingslashit($path));
        }

        return $scheme . '://' . $host . $path;
    }

    /**
     * Reports whether the Tools screen has the cache turned on.
     *
     * @since 0.10.0
     *
     * @return bool True when serving and writing are allowed.
     */
    public function enabled(): bool
    {
        $value = get_option(self::OPTION_ENABLED, '1');

        if (is_array($value)) {
            return in_array('1', array_map('strval', $value), true);
        }

        return (string) $value === '1';
    }

    /**
     * Page IDs cleared together with a post type.
     *
     * @since 0.10.0
     *
     * @param string $postType Post type slug.
     *
     * @return list<int>
     */
    public function linked(string $postType): array
    {
        $value = get_option(self::pagesOption($postType), []);

        if (!is_array($value)) {
            return [];
        }

        $ids = [];

        foreach ($value as $id) {
            $id = (int) $id;

            if ($id > 0 && get_post_type($id) === 'page') {
                $ids[] = $id;
            }
        }

        return $ids;
    }

    /**
     * Public post types shown on the Tools screen.
     *
     * @since 0.10.0
     *
     * @return array<string, string> Slug mapped to the plural label.
     */
    public function postTypeChoices(): array
    {
        $choices = [];

        foreach (get_post_types(['public' => true], 'objects') as $slug => $object) {
            if ($slug === 'attachment') {
                continue;
            }

            $choices[$slug] = $object->labels->name;
        }

        asort($choices);

        return $choices;
    }

    /**
     * Published pages that can be linked to a post type.
     *
     * @since 0.10.0
     *
     * @return array<int, string> Page ID mapped to the title.
     */
    public function pageChoices(): array
    {
        $choices = [];
        $pages = get_posts([
            'post_type' => 'page',
            'post_status' => 'publish',
            'posts_per_page' => -1,
            'orderby' => 'title',
            'order' => 'ASC',
        ]);

        foreach ($pages as $page) {
            $choices[(int) $page->ID] = $page->post_title !== '' ? $page->post_title : '#' . $page->ID;
        }

        return $choices;
    }

    /**
     * Clears one cached URL or the whole directory from the Tools screen.
     *
     * Settings are saved by the AdminPage form through `options.php`.
     *
     * @since 0.10.0
     */
    public function handlePost(): void
    {
        if (($_GET['page'] ?? '') !== self::MENU_SLUG || ($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
            return;
        }

        if (!current_user_can('manage_options')) {
            return;
        }

        check_admin_referer('wpb_page_cache');

        $hash = sanitize_text_field((string) wp_unslash($_POST['wpb_page_cache_hash'] ?? ''));
        $action = sanitize_key((string) wp_unslash($_POST['wpb_page_cache_action'] ?? ''));

        if ($hash !== '') {
            $this->forget($hash);
            $message = 'forgotten';
        } elseif ($action === 'flush') {
            $this->flush();
            $message = 'flushed';
        } else {
            return;
        }

        wp_safe_redirect(add_query_arg(
            'wpb-cache',
            $message,
            admin_url('tools.php?page=' . self::MENU_SLUG)
        ));
        exit;
    }

    /**
     * Reads the current request without its query string.
     *
     * @since 0.10.0
     *
     * @return string Normalized URL, or an empty string.
     */
    private function requestUrl(): string
    {
        $host = (string) ($_SERVER['HTTP_HOST'] ?? '');
        $path = (string) strtok((string) ($_SERVER['REQUEST_URI'] ?? '/'), '?');
        $scheme = is_ssl() ? 'https' : 'http';

        return $this->normalize($scheme . '://' . $host . $path);
    }

    /**
     * Reports whether this request may read or write the cache.
     *
     * @since 0.10.0
     */
    private function shouldCache(): bool
    {
        if (
            !$this->enabled()
            || is_admin()
            || wp_doing_ajax()
            || wp_doing_cron()
            || is_user_logged_in()
            || is_preview()
            || is_customize_preview()
            || is_feed()
            || is_trackback()
            || is_robots()
            || is_favicon()
            || is_embed()
        ) {
            return false;
        }

        if ((defined('REST_REQUEST') && REST_REQUEST) || $this->pageDisablesCache()) {
            return false;
        }

        $method = strtoupper((string) ($_SERVER['REQUEST_METHOD'] ?? 'GET'));

        return $method === 'GET' && (string) ($_SERVER['QUERY_STRING'] ?? '') === '';
    }

    /**
     * Reports whether the captured response is worth storing.
     *
     * @since 0.10.0
     *
     * @param string $html Captured output.
     */
    private function shouldStore(string $html): bool
    {
        if (!$this->shouldCache() || $html === '' || is_404() || http_response_code() !== 200) {
            return false;
        }

        foreach (headers_list() as $header) {
            if (stripos($header, 'Content-Type:') === 0 && stripos($header, 'text/html') === false) {
                return false;
            }
        }

        return true;
    }

    /**
     * Reports whether a post type participates in cache clearing.
     *
     * @since 0.10.0
     *
     * @param \WP_Post $post Post being saved or deleted.
     */
    private function tracks(\WP_Post $post): bool
    {
        if ($post->post_type === 'attachment' || $post->post_status === 'auto-draft' || wp_is_post_revision($post)) {
            return false;
        }

        $object = get_post_type_object($post->post_type);

        return $object !== null && $object->public;
    }

    /**
     * Absolute directory for the HTML and meta files.
     *
     * @since 0.10.0
     */
    private function directory(): string
    {
        return WP_CONTENT_DIR . '/cache/wpb';
    }

    /**
     * Hashes a normalized URL.
     *
     * @since 0.10.0
     *
     * @param string $url Normalized URL.
     */
    private function hash(string $url): string
    {
        return md5($url);
    }

    /**
     * Path of the HTML file for a URL.
     *
     * @since 0.10.0
     *
     * @param string $url Public URL.
     */
    private function htmlFile(string $url): string
    {
        return $this->directory() . '/' . $this->hash($this->normalize($url)) . '.html';
    }

    /**
     * Deletes the HTML and meta files for a hash.
     *
     * @since 0.10.0
     *
     * @param string $hash MD5 file name without an extension.
     */
    private function deleteHash(string $hash): void
    {
        foreach (['html', 'json'] as $extension) {
            $file = $this->directory() . '/' . $hash . '.' . $extension;

            if (is_file($file)) {
                unlink($file);
            }
        }
    }
}
