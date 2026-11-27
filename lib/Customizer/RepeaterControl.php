<?php

/**
 * Customizer control for a repeater setting.
 *
 * @author  Renato Rodrigues Jr <juniorenato@msn.com>
 * @license GPL-3.0-or-later
 * @package WPB\Customizer
 */

namespace WPB\Customizer;

/**
 * Renders the repeater used by meta boxes inside Appearance → Customize.
 *
 * The markup matches the admin repeater so the existing row script can add,
 * remove, and reorder items. Image and file buttons use the admin media script.
 *
 * @author Renato Rodrigues Jr <juniorenato@msn.com>
 */
class RepeaterControl extends \WP_Customize_Control
{
    /**
     * Control type used by the customizer script.
     *
     * @var string
     */
    public $type = 'wpb_repeater';

    /**
     * Repeater schema passed from the section.
     *
     * @var array<string, mixed>
     */
    public array $schema = [];

    /**
     * Prints the control body.
     */
    public function render_content(): void
    {
        if ($this->label !== '') {
            echo '<span class="customize-control-title">' . esc_html($this->label) . '</span>';
        }

        if ($this->description !== '') {
            echo '<span class="description customize-control-description">' . esc_html($this->description) . '</span>';
        }

        $this->renderLevel($this->schema, Repeater::rows($this->schema, $this->value()));
    }

    /**
     * Prints one repeater level.
     *
     * @param array<string, mixed>        $schema Repeater schema.
     * @param list<array<string, mixed>>  $rows   Rows to show.
     */
    public function renderLevel(array $schema, array $rows): void
    {
        $name = (string) ($schema['name'] ?? '');
        ?>
        <div
            class="wpb-repeater"
            data-name="<?php echo esc_attr($name); ?>"
            data-min="<?php echo esc_attr((string) ($schema['min'] ?? 0)); ?>"
            data-max="<?php echo esc_attr((string) ($schema['max'] ?? 0)); ?>">
            <div class="wpb-repeater__items">
                <?php foreach ($rows as $index => $row) : ?>
                    <?php $this->renderItem($schema, $row, $name . '[' . $index . ']'); ?>
                <?php endforeach; ?>
            </div>
            <template>
                <?php $this->renderItem($schema, [], $name . '[__i__]'); ?>
            </template>
            <input type="hidden" class="wpb-repeater__present" name="<?php echo esc_attr($name); ?>[__present]" value="1">
            <p class="wpb-repeater__footer">
                <button type="button" class="button button-primary wpb-repeater__add">
                    <?php echo esc_html(__('Add', 'wpb') . ' ' . (string) ($schema['label'] ?? '')); ?>
                </button>
            </p>
        </div>
        <?php
    }

    /**
     * Prints one row.
     *
     * @param array<string, mixed> $schema  Repeater schema.
     * @param array<string, mixed> $row     Saved row. An empty array uses field defaults.
     * @param string               $rowName Request name for this row.
     */
    public function renderItem(array $schema, array $row, string $rowName): void
    {
        ?>
        <div class="wpb-repeater__item">
            <div class="wpb-repeater__fields">
                <?php foreach ($schema['fields'] ?? [] as $child) : ?>
                    <?php
                    if (!is_array($child) || !isset($child['name'])) {
                        continue;
                    }

                    $this->renderField($child, $row, $rowName);
                    ?>
                <?php endforeach; ?>
            </div>
            <p class="wpb-repeater__toolbar">
                <button type="button" class="button wpb-repeater__up"><?php esc_html_e('Move up', 'wpb'); ?></button>
                <button type="button" class="button wpb-repeater__down"><?php esc_html_e('Move down', 'wpb'); ?></button>
                <button type="button" class="button wpb-repeater__remove"><?php esc_html_e('Remove', 'wpb'); ?></button>
            </p>
        </div>
        <?php
    }

    /**
     * Prints one cell.
     *
     * @param array<string, mixed> $field   Field schema.
     * @param array<string, mixed> $row     Saved row.
     * @param string               $rowName Request name for the row.
     */
    public function renderField(array $field, array $row, string $rowName): void
    {
        $key = (string) $field['name'];
        $type = (string) ($field['type'] ?? 'text');
        $inputName = $rowName . '[' . $key . ']';
        $inputId = 'field_' . $inputName;
        $value = array_key_exists($key, $row) ? $row[$key] : ($field['default'] ?? null);
        ?>
        <div class="wpb-repeater__field" data-key="<?php echo esc_attr($key); ?>" data-type="<?php echo esc_attr($type); ?>">
            <?php if ($type === 'repeater') : ?>
                <p class="wpb-repeater__label"><strong><?php echo esc_html((string) ($field['label'] ?? '')); ?></strong></p>
                <?php $this->renderLevel($field, Repeater::rows($field, $value)); ?>
            <?php else : ?>
                <label for="<?php echo esc_attr($inputId); ?>"><?php echo esc_html((string) ($field['label'] ?? '')); ?></label>
                <?php $this->renderInput($type, $field, $value, $inputName, $inputId); ?>
            <?php endif; ?>
            <?php if (!empty($field['description'])) : ?>
                <p class="description" id="<?php echo esc_attr($inputName); ?>-description"><?php echo esc_html((string) $field['description']); ?></p>
            <?php endif; ?>
        </div>
        <?php
    }

    /**
     * Prints the input for one scalar cell.
     *
     * @param string               $type      Field type.
     * @param array<string, mixed> $field     Field schema.
     * @param mixed                $value     Current value.
     * @param string               $inputName Request name.
     * @param string               $inputId   Element id.
     */
    private function renderInput(string $type, array $field, mixed $value, string $inputName, string $inputId): void
    {
        $described = !empty($field['description'])
            ? ' aria-describedby="' . esc_attr($inputName . '-description') . '"'
            : '';

        if ($type === 'textarea' || $type === 'html') {
            echo '<textarea name="' . esc_attr($inputName) . '" id="' . esc_attr($inputId) . '"' . $described . '>';
            echo esc_textarea(is_scalar($value) ? (string) $value : '');
            echo '</textarea>';

            return;
        }

        if ($type === 'select') {
            $this->renderSelect($field, $value, $inputName, $inputId, $described);

            return;
        }

        if ($type === 'radio') {
            $this->renderRadio($field, $value, $inputName, $inputId);

            return;
        }

        if ($type === 'checkbox') {
            echo '<input type="checkbox" name="' . esc_attr($inputName) . '" id="' . esc_attr($inputId) . '" value="1"' . $described;
            echo $this->isChecked($value) ? ' checked' : '';
            echo '>';

            return;
        }

        if ($type === 'image' || $type === 'file') {
            $this->renderMedia($type, $value, $inputName, $inputId, $described);

            return;
        }

        if ($type === 'color') {
            $color = is_scalar($value) ? (string) $value : '';
            echo '<input type="text" class="wpb-color-field" data-default-color="' . esc_attr($color) . '" name="' . esc_attr($inputName) . '" id="' . esc_attr($inputId) . '" value="' . esc_attr($color) . '"' . $described . '>';

            return;
        }

        $inputType = $type === 'number' ? 'number' : 'text';
        $step = $type === 'number' ? ' step="any"' : '';
        $text = is_scalar($value) ? (string) $value : '';
        echo '<input type="' . esc_attr($inputType) . '"' . $step . ' name="' . esc_attr($inputName) . '" id="' . esc_attr($inputId) . '" value="' . esc_attr($text) . '"' . $described . '>';
    }

    /**
     * Prints a select cell.
     *
     * @param array<string, mixed> $field     Field schema.
     * @param mixed                $value     Current value.
     * @param string               $inputName Request name.
     * @param string               $inputId   Element id.
     * @param string               $described Description attribute.
     */
    private function renderSelect(array $field, mixed $value, string $inputName, string $inputId, string $described): void
    {
        $selected = is_scalar($value) ? (string) $value : '';
        $choices = is_array($field['choices'] ?? null) ? $field['choices'] : [];

        echo '<select name="' . esc_attr($inputName) . '" id="' . esc_attr($inputId) . '"' . $described . '>';

        if (!array_key_exists('', $choices)) {
            echo '<option value=""' . ($selected === '' ? ' selected' : '') . '></option>';
        }

        foreach ($choices as $choice => $label) {
            echo '<option value="' . esc_attr((string) $choice) . '"';
            echo (string) $choice === $selected ? ' selected' : '';
            echo '>' . esc_html((string) $label) . '</option>';
        }

        echo '</select>';
    }

    /**
     * Prints a radio cell.
     *
     * @param array<string, mixed> $field     Field schema.
     * @param mixed                $value     Current value.
     * @param string               $inputName Request name.
     * @param string               $inputId   Element id.
     */
    private function renderRadio(array $field, mixed $value, string $inputName, string $inputId): void
    {
        $selected = is_scalar($value) ? (string) $value : '';
        $choices = is_array($field['choices'] ?? null) ? $field['choices'] : [];

        echo '<fieldset id="' . esc_attr($inputId) . '" class="wpb-radio-group">';
        echo '<legend class="screen-reader-text">' . esc_html((string) ($field['label'] ?? '')) . '</legend>';

        foreach ($choices as $choice => $label) {
            echo '<label><input type="radio" name="' . esc_attr($inputName) . '" value="' . esc_attr((string) $choice) . '"';
            echo (string) $choice === $selected ? ' checked' : '';
            echo '> ' . esc_html((string) $label) . '</label><br>';
        }

        echo '</fieldset>';
    }

    /**
     * Prints an image or file cell.
     *
     * @param string $type      `image` or `file`.
     * @param mixed  $value     Attachment ID.
     * @param string $inputName Request name.
     * @param string $inputId   Element id.
     * @param string $described Description attribute.
     */
    private function renderMedia(string $type, mixed $value, string $inputName, string $inputId, string $described): void
    {
        $id = is_numeric($value) ? (int) $value : 0;
        $file = $type === 'file';
        $root = $file ? 'wpb-file-field' : 'wpb-image-field';
        $attachment = $id > 0 ? $this->attachment($id, $file) : ['url' => '', 'label' => '', 'alt' => ''];
        $hasValue = $attachment['url'] !== '' || ($file && $attachment['label'] !== '');
        $select = __($file ? 'Select file' : 'Select image', 'wpb');
        $replace = __($file ? 'Replace file' : 'Replace image', 'wpb');
        $remove = __($file ? 'Remove file' : 'Remove image', 'wpb');
        ?>
        <div
            class="<?php echo esc_attr($root); ?>"
            <?php echo $file ? '' : 'data-library="image"'; ?>
            data-multiple="0"
            data-name="<?php echo esc_attr($inputName); ?>"
            data-select="<?php echo esc_attr($select); ?>"
            data-replace="<?php echo esc_attr($replace); ?>"
            data-add="<?php echo esc_attr($select); ?>"
            data-remove="<?php echo esc_attr($remove); ?>">
            <div class="<?php echo esc_attr($root); ?>__preview">
                <?php if (!$file && $attachment['url'] !== '') : ?>
                    <img src="<?php echo esc_url($attachment['url']); ?>" alt="<?php echo esc_attr($attachment['alt']); ?>">
                <?php elseif ($file && $attachment['url'] !== '') : ?>
                    <a class="wpb-file-field__name" href="<?php echo esc_url($attachment['url']); ?>" target="_blank" rel="noopener noreferrer"><?php echo esc_html($attachment['label'] !== '' ? $attachment['label'] : $attachment['url']); ?></a>
                <?php elseif ($file && $attachment['label'] !== '') : ?>
                    <span class="wpb-file-field__name"><?php echo esc_html($attachment['label']); ?></span>
                <?php endif; ?>
            </div>
            <input type="hidden" class="<?php echo esc_attr($root); ?>__id" name="<?php echo esc_attr($inputName); ?>" value="<?php echo esc_attr($id > 0 ? (string) $id : ''); ?>">
            <p class="<?php echo esc_attr($root); ?>__actions">
                <button type="button" class="button <?php echo esc_attr($root); ?>__select" id="<?php echo esc_attr($inputId); ?>"<?php echo $described; ?>>
                    <?php echo esc_html($hasValue ? $replace : $select); ?>
                </button>
                <button type="button" class="button-link <?php echo esc_attr($root); ?>__remove"<?php echo $hasValue ? '' : ' hidden'; ?>>
                    <?php echo esc_html($remove); ?>
                </button>
            </p>
        </div>
        <?php
    }

    /**
     * Reads the preview data for one attachment.
     *
     * @param int  $id   Attachment ID.
     * @param bool $file True when any file is accepted.
     *
     * @return array{url: string, label: string, alt: string} Preview data.
     */
    private function attachment(int $id, bool $file): array
    {
        $url = '';
        $label = '';
        $alt = '';

        if ($file && function_exists('wp_get_attachment_url')) {
            $resolved = wp_get_attachment_url($id);
            $url = is_string($resolved) ? $resolved : '';
        }

        if (!$file && function_exists('wp_get_attachment_image_url')) {
            $resolved = wp_get_attachment_image_url($id, 'medium');
            $url = is_string($resolved) ? $resolved : '';
        }

        if ($file && function_exists('get_the_title')) {
            $label = trim((string) get_the_title($id));
        }

        if ($label === '' && $url !== '') {
            $label = function_exists('wp_basename') ? (string) wp_basename($url) : $url;
        }

        if (!$file && function_exists('get_post_meta')) {
            $alt = (string) get_post_meta($id, '_wp_attachment_image_alt', true);
        }

        return [
            'url'   => $url,
            'label' => $label,
            'alt'   => $alt,
        ];
    }

    /**
     * Reports whether a checkbox value is checked.
     *
     * @param mixed $value Stored value.
     *
     * @return bool True when the box is checked.
     */
    private function isChecked(mixed $value): bool
    {
        if (function_exists('rest_sanitize_boolean')) {
            return rest_sanitize_boolean($value);
        }

        return $value === true || $value === 1 || $value === '1';
    }
}
