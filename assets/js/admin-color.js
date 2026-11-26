/**
 * Turns color inputs into WordPress color pickers.
 *
 * The script loads only when a color field is rendered. Inputs inside a
 * repeater template stay plain until the row is added.
 */
(function () {
    if (window.wpbAdminColorBound) {
        return;
    }

    window.wpbAdminColorBound = true;

    function init(scope) {
        if (!scope || !window.jQuery || !jQuery.fn.wpColorPicker) {
            return;
        }

        jQuery(scope).find('.wpb-color-field').each(function () {
            var input = jQuery(this);

            if (
                input.hasClass('wp-color-picker')
                || input.closest('.wp-picker-container').length
                || input.closest('.customize-control-wpb_repeater').length
            ) {
                return;
            }

            input.wpColorPicker();
        });
    }

    function boot() {
        init(document);
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', boot);
    } else {
        boot();
    }

    document.addEventListener('wpb-repeater-change', function (event) {
        init(event.target);
    });
}());
