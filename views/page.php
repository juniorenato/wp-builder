<?php
/**
 * Admin settings page layout.
 *
 * @package WPB\Pages
 *
 * @see https://developer.wordpress.org/plugins/settings/settings-api/
 * @see https://developer.wordpress.org/reference/functions/settings_fields/
 * @see https://developer.wordpress.org/reference/functions/do_settings_sections/
 * @see https://developer.wordpress.org/reference/functions/submit_button/
 */
?>
<div class="wrap">
<form method="post" action="options.php">

    <h1><?php echo esc_html($this->pageTitle); ?></h1>

    <?php settings_fields($this->menuSlug . '-group'); ?>
    <?php do_settings_sections($this->menuSlug . '-group'); ?>

    <table class="form-table" role="presentation">
        <?php $this->renderFields('table'); ?>
    </table>

    <?php submit_button(); ?>
</form>
</div>
