<?php
/**
 * Dashboard list of cached URLs.
 *
 * @package WPB\Performance
 *
 * @var \WPB\Performance\PageCache $this
 */

$entries = $this->entries();
?>
<?php if ($entries === []) { ?>
    <p><?php echo esc_html(__('No cached pages.', 'wpb')); ?></p>
<?php } else { ?>
    <ul>
        <?php foreach ($entries as $entry) { ?>
            <li>
                <a href="<?php echo esc_url($entry['url']); ?>"><?php echo esc_html($entry['url']); ?></a><br>
                <span class="description">
                    <?php echo esc_html(wp_date(get_option('date_format') . ' ' . get_option('time_format'), $entry['created'])); ?>
                </span>
            </li>
        <?php } ?>
    </ul>
<?php } ?>
<p>
    <a href="<?php echo esc_url(admin_url('tools.php?page=' . \WPB\Performance\PageCache::MENU_SLUG)); ?>">
        <?php echo esc_html(__('Manage cache', 'wpb')); ?>
    </a>
</p>
