/**
 * Opens the WordPress media modal for an image field and stores attachment IDs.
 *
 * One image replaces the current attachment. Several images are added to the list.
 */
(function () {
    if (window.wpbAdminImageBound) {
        return;
    }

    window.wpbAdminImageBound = true;

    function isMultiple(field) {
        return field.getAttribute('data-multiple') === '1';
    }

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

    function selectedIds(field) {
        var ids = [];

        Array.prototype.forEach.call(field.querySelectorAll('.wpb-image-field__id'), function (input) {
            if (input.value) {
                ids.push(input.value);
            }
        });

        return ids;
    }

    function updateButton(field) {
        var select = field.querySelector('.wpb-image-field__select');
        var count = selectedIds(field).length;

        if (!select || !isMultiple(field)) {
            return;
        }

        select.textContent = count
            ? (field.getAttribute('data-add') || 'Add images')
            : (field.getAttribute('data-select') || 'Select images');
    }

    function paint(field, attachment) {
        var input = field.querySelector('.wpb-image-field__id');
        var preview = field.querySelector('.wpb-image-field__preview');
        var select = field.querySelector('.wpb-image-field__select');
        var remove = field.querySelector('.wpb-image-field__actions .wpb-image-field__remove');
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

    function itemElement(field, attachment) {
        var item = document.createElement('figure');
        var image = document.createElement('img');
        var input = document.createElement('input');
        var remove = document.createElement('button');
        var removeLabel = field.getAttribute('data-remove') || 'Remove image';

        item.className = 'wpb-image-field__item';
        image.src = attachmentUrl(attachment);
        image.alt = attachment.alt || '';
        input.type = 'hidden';
        input.className = 'wpb-image-field__id';
        input.name = field.getAttribute('data-name') || '';
        input.value = String(attachment.id);
        remove.type = 'button';
        remove.className = 'button-link wpb-image-field__remove';
        remove.setAttribute('aria-label', removeLabel);
        remove.textContent = removeLabel;
        item.appendChild(image);
        item.appendChild(input);
        item.appendChild(remove);

        return item;
    }

    function appendAttachments(field, attachments) {
        var preview = field.querySelector('.wpb-image-field__preview');
        var seen = {};

        if (!preview) {
            return;
        }

        selectedIds(field).forEach(function (id) {
            seen[id] = true;
        });

        attachments.forEach(function (attachment) {
            var id = attachment && attachment.id ? String(attachment.id) : '';

            if (!id || seen[id]) {
                return;
            }

            seen[id] = true;
            preview.appendChild(itemElement(field, attachment));
        });

        updateButton(field);
    }

    function openFrame(field) {
        var multiple = isMultiple(field);
        var current = selectedIds(field);
        var hasImage = current.length > 0;
        var frame = wp.media({
            title: multiple
                ? (hasImage
                    ? (field.getAttribute('data-add') || 'Add images')
                    : (field.getAttribute('data-select') || 'Select images'))
                : (hasImage
                    ? (field.getAttribute('data-replace') || 'Replace image')
                    : (field.getAttribute('data-select') || 'Select image')),
            button: {
                text: multiple
                    ? (field.getAttribute('data-select') || 'Select images')
                    : (field.getAttribute('data-select') || 'Select image')
            },
            library: {
                type: 'image'
            },
            multiple: multiple
        });

        frame.on('open', function () {
            var selection;

            if (multiple || !hasImage) {
                return;
            }

            selection = frame.state().get('selection');
            current.forEach(function (id) {
                var attachment = wp.media.attachment(id);
                attachment.fetch();
                selection.add(attachment);
            });
        });

        frame.on('select', function () {
            var models = frame.state().get('selection').toArray();
            var attachments = models.map(function (model) {
                return model.toJSON();
            });

            if (multiple) {
                appendAttachments(field, attachments);
                return;
            }

            paint(field, attachments.length ? attachments[0] : null);
        });

        frame.open();
    }

    document.addEventListener('click', function (event) {
        var target = event.target;
        var button;
        var field;
        var item;

        if (!target || !target.closest) {
            return;
        }

        field = target.closest('.wpb-image-field');

        if (!field) {
            return;
        }

        button = target.closest('button');

        if (button && field.contains(button) && button.classList.contains('wpb-image-field__remove')) {
            event.preventDefault();

            if (isMultiple(field)) {
                item = button.closest('.wpb-image-field__item');

                if (item) {
                    item.remove();
                }

                updateButton(field);
                return;
            }

            paint(field, null);
            return;
        }

        if (button && button.classList.contains('wpb-image-field__select')) {
            event.preventDefault();

            if (window.wp && wp.media) {
                openFrame(field);
            }

            return;
        }

        if (target.closest('.wpb-image-field__preview img') && window.wp && wp.media) {
            event.preventDefault();
            openFrame(field);
        }
    });
}());
