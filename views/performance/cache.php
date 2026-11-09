<?php
/**
 * Tools → Cache screen.
 *
 * @package WPB\Performance
 *
 * @var \WPB\Performance\CacheScreen $this
 */

$cache = $this->cache();
$notice = sanitize_key((string) ($_GET['wpb-cache'] ?? ''));
$messages = [
    'flushed' => __('Cache cleared.', 'wpb'),
    'forgotten' => __('Page removed from the cache.', 'wpb'),
];
$entries = $cache->entries();
?>
<div class="wrap">
    <h1><?php echo esc_html($this->pageTitle); ?></h1>

    <?php if (isset($messages[$notice])) { ?>
        <div class="notice notice-success is-dismissible"><p><?php echo esc_html($messages[$notice]); ?></p></div>
    <?php } ?>

    <?php if (!$cache->enabled()) { ?>
        <div class="notice notice-warning"><p><?php echo esc_html(__('Page cache is off. Existing files stay on disk until you clear them.', 'wpb')); ?></p></div>
    <?php } ?>

    <form method="post" action="options.php">
        <?php settings_fields($this->menuSlug . '-group'); ?>
        <?php do_settings_sections($this->menuSlug . '-group'); ?>

        <p class="description">
            <?php echo esc_html(__('Saving a post also clears that post, the post type archive and its pagination, and the pages selected here.', 'wpb')); ?>
        </p>

        <table class="form-table" role="presentation">
            <?php $this->renderFields('table'); ?>
        </table>

        <?php submit_button(); ?>
    </form>

    <h2><?php echo esc_html(__('Cached pages', 'wpb')); ?></h2>

    <form method="post" action="<?php echo esc_url(admin_url('tools.php?page=' . $this->menuSlug)); ?>">
        <?php wp_nonce_field('wpb_page_cache'); ?>

        <p>
            <button type="submit" name="wpb_page_cache_action" value="flush" class="button">
                <?php echo esc_html(__('Clear all cache', 'wpb')); ?>
            </button>
        </p>

        <table class="widefat striped">
            <thead>
                <tr>
                    <th scope="col"><?php echo esc_html(__('URL', 'wpb')); ?></th>
                    <th scope="col"><?php echo esc_html(__('Stored', 'wpb')); ?></th>
                    <th scope="col"><?php echo esc_html(__('Clear', 'wpb')); ?></th>
                </tr>
            </thead>
            <tbody>
                <?php if ($entries === []) { ?>
                    <tr><td colspan="3"><?php echo esc_html(__('No cached pages.', 'wpb')); ?></td></tr>
                <?php } ?>
                <?php foreach ($entries as $entry) { ?>
                    <tr>
                        <td><a href="<?php echo esc_url($entry['url']); ?>"><?php echo esc_html($entry['url']); ?></a></td>
                        <td><?php echo esc_html(wp_date(get_option('date_format') . ' ' . get_option('time_format'), $entry['created'])); ?></td>
                        <td>
                            <button type="submit" name="wpb_page_cache_hash" value="<?php echo esc_attr($entry['hash']); ?>" class="button button-small">
                                <?php echo esc_html(__('Clear', 'wpb')); ?>
                            </button>
                        </td>
                    </tr>
                <?php } ?>
            </tbody>
        </table>
    </form>
</div>
