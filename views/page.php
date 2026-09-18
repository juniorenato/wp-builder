<div class="wrap">
<form method="post" action="options.php">

    <h1><?= esc_html($this->pageTitle) ?></h1>

    <?php settings_fields($this->menuSlug .'-group') ?>
    <?php do_settings_sections($this->menuSlug .'-group') ?>

    <table class="form-table" role="presentation">
        <?php $this->renderFields('table'); ?>
    </table>

    <?php submit_button(); ?>
</form>
</div>
