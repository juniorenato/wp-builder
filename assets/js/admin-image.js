/**
 * Opens the WordPress media modal for an image field and stores the attachment ID.
 */
(function () {
    if (window.wpbAdminImageBound) {
        return;
    }

    window.wpbAdminImageBound = true;

    function attachmentUrl(attachment) {
        var sizes = attachment.sizes || {};

        if (sizes.medium && sizes.medium.url) {
            return sizes.medium.url;
        }

        if (sizes.thumbnail && sizes.thumbnail.url) {
            return sizes.thumbnail.url;
        }

        return attachment.url || '';
    }

    function paint(field, attachment) {
        var input = field.querySelector('.wpb-image-field__id');
        var preview = field.querySelector('.wpb-image-field__preview');
        var select = field.querySelector('.wpb-image-field__select');
        var remove = field.querySelector('.wpb-image-field__remove');
        var id = attachment && attachment.id ? String(attachment.id) : '';
        var url = id ? attachmentUrl(attachment) : '';
        var image;

        if (input) {
            input.value = id;
        }

        if (preview) {
            preview.replaceChildren();

            if (url) {
                image = document.createElement('img');
                image.src = url;
                image.alt = attachment.alt || '';
                preview.appendChild(image);
            }
        }

        if (select) {
            select.textContent = id
                ? (field.getAttribute('data-replace') || 'Replace image')
                : (field.getAttribute('data-select') || 'Select image');
        }

        if (remove) {
            remove.hidden = id === '';
        }
    }

    function openFrame(field) {
        var input = field.querySelector('.wpb-image-field__id');
        var current = input ? parseInt(input.value, 10) : 0;
        var hasImage = current > 0;
        var frame = wp.media({
            title: hasImage
                ? (field.getAttribute('data-replace') || 'Replace image')
                : (field.getAttribute('data-select') || 'Select image'),
            button: {
                text: field.getAttribute('data-select') || 'Select image'
            },
            library: {
                type: 'image'
            },
            multiple: false
        });

        frame.on('open', function () {
            var selection;
            var attachment;

            if (!hasImage) {
                return;
            }

            selection = frame.state().get('selection');
            attachment = wp.media.attachment(current);
            attachment.fetch();
            selection.add(attachment);
        });

        frame.on('select', function () {
            var selected = frame.state().get('selection').first();

            paint(field, selected ? selected.toJSON() : null);
        });

        frame.open();
    }

    document.addEventListener('click', function (event) {
        var target = event.target;
        var button;
        var field;

        if (!target || !target.closest) {
            return;
        }

        if (target.closest('.wpb-image-field__preview')) {
            field = target.closest('.wpb-image-field');

            if (field && field.querySelector('.wpb-image-field__preview img') && window.wp && wp.media) {
                event.preventDefault();
                openFrame(field);
            }

            return;
        }

        button = target.closest('button');

        if (!button) {
            return;
        }

        field = button.closest('.wpb-image-field');

        if (!field) {
            return;
        }

        if (button.classList.contains('wpb-image-field__select')) {
            event.preventDefault();

            if (window.wp && wp.media) {
                openFrame(field);
            }

            return;
        }

        if (button.classList.contains('wpb-image-field__remove')) {
            event.preventDefault();
            paint(field, null);
        }
    });
}());
