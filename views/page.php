<div class="wrap">
<form method="post" action="options.php">

    <h1><?= $this->pageTitle ?></h1>

    <?php settings_fields($this->menuSlug .'-group') ?>
    <?php do_settings_sections($this->menuSlug .'-group') ?>

    <table class="form-table" role="presentation">

        <?php foreach($this->fields as $field) : ?>
            <?php $this->field = $field; ?>
            <?php require 'form/table-'. $field['type'] .'.php' ?>
        <?php endforeach ?>

    </table>

    <?php submit_button(); ?>
</form>
</div>
