<?php
/**
 * Dashboard box that regenerates existing images.
 *
 * @package WPB\Performance
 *
 * @var \WPB\Performance\Performance $this
 */

$count = $this->imageAttachmentCount();
?>
<div
    id="wpb-regenerate-images"
    data-ajax="<?php echo esc_url(admin_url('admin-ajax.php')); ?>"
    data-nonce="<?php echo esc_attr(wp_create_nonce('wpb_regenerate_images')); ?>"
    data-error="<?php echo esc_attr(__('The images could not be regenerated.', 'wpb')); ?>">
    <p>
        <?php
        echo esc_html(sprintf(
            /* translators: %d: longest image edge in pixels */
            __('Rebuilds existing JPEG, PNG, WebP, and AVIF uploads. The longest edge is %d pixels.', 'wpb'),
            $this->bigImageSizeThreshold()
        ));
        ?>
    </p>
    <p class="description" data-status>
        <?php
        echo esc_html(sprintf(
            _n(
                '%d image can be regenerated.',
                '%d images can be regenerated.',
                $count,
                'wpb'
            ),
            $count
        ));
        ?>
    </p>
    <p>
        <button type="button" class="button button-primary"<?php echo $count < 1 ? ' disabled' : ''; ?>>
            <?php echo esc_html(__('Regenerate images', 'wpb')); ?>
        </button>
    </p>
</div>
