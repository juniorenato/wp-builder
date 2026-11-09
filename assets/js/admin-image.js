/**
 * Opens the WordPress media modal for an image or file field and stores attachment IDs.
 *
 * One attachment replaces the current value. Several attachments are added to the list.
 * An image field limits the library to images. A file field accepts any library item.
 */
(function () {
    if (window.wpbAdminImageBound) {
        return;
    }

    window.wpbAdminImageBound = true;

    function isFile(field) {
        return field.classList.contains('wpb-file-field');
    }

    function isMultiple(field) {
        return field.getAttribute('data-multiple') === '1';
    }

    function part(field, name) {
        var root = isFile(field) ? 'wpb-file-field' : 'wpb-image-field';

        return '.' + root + '__' + name;
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

    function fileLabel(attachment) {
        return attachment.filename || attachment.title || attachment.url || String(attachment.id);
    }

    function selectedIds(field) {
        var ids = [];

        Array.prototype.forEach.call(field.querySelectorAll(part(field, 'id')), function (input) {
            if (input.value) {
                ids.push(input.value);
            }
        });

        return ids;
    }

    function updateButton(field) {
        var select = field.querySelector(part(field, 'select'));
        var count = selectedIds(field).length;

        if (!select || !isMultiple(field)) {
            return;
        }

        select.textContent = count
            ? (field.getAttribute('data-add') || 'Add')
            : (field.getAttribute('data-select') || 'Select');
    }

    function imageNode(attachment) {
        var url = attachmentUrl(attachment);
        var image;

        if (!url) {
            return null;
        }

        image = document.createElement('img');
        image.src = url;
        image.alt = attachment.alt || '';

        return image;
    }

    function fileNode(attachment) {
        var link = document.createElement('a');
        var url = attachment.url || '';

        link.className = 'wpb-file-field__name';
        link.textContent = fileLabel(attachment);

        if (url) {
            link.href = url;
            link.target = '_blank';
            link.rel = 'noopener noreferrer';
        }

        return link;
    }

    function paint(field, attachment) {
        var input = field.querySelector(part(field, 'id'));
        var preview = field.querySelector(part(field, 'preview'));
        var select = field.querySelector(part(field, 'select'));
        var remove = field.querySelector(part(field, 'actions') + ' ' + part(field, 'remove'));
        var id = attachment && attachment.id ? String(attachment.id) : '';
        var node = null;

        if (input) {
            input.value = id;
        }

        if (preview) {
            preview.replaceChildren();

            if (id) {
                node = isFile(field) ? fileNode(attachment) : imageNode(attachment);
            }

            if (node) {
                preview.appendChild(node);
            }
        }

        if (select) {
            select.textContent = id
                ? (field.getAttribute('data-replace') || 'Replace')
                : (field.getAttribute('data-select') || 'Select');
        }

        if (remove) {
            remove.hidden = id === '';
        }
    }

    function itemElement(field, attachment) {
        var item = document.createElement(isFile(field) ? 'div' : 'figure');
        var input = document.createElement('input');
        var remove = document.createElement('button');
        var removeLabel = field.getAttribute('data-remove') || 'Remove';
        var node = isFile(field) ? fileNode(attachment) : imageNode(attachment);

        item.className = (isFile(field) ? 'wpb-file-field' : 'wpb-image-field') + '__item';

        if (node) {
            item.appendChild(node);
        }

        input.type = 'hidden';
        input.className = (isFile(field) ? 'wpb-file-field' : 'wpb-image-field') + '__id';
        input.name = field.getAttribute('data-name') || '';
        input.value = String(attachment.id);
        remove.type = 'button';
        remove.className = 'button-link ' + (isFile(field) ? 'wpb-file-field' : 'wpb-image-field') + '__remove';
        remove.setAttribute('aria-label', removeLabel);
        remove.textContent = removeLabel;
        item.appendChild(input);
        item.appendChild(remove);

        return item;
    }

    function appendAttachments(field, attachments) {
        var preview = field.querySelector(part(field, 'preview'));
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
        var hasValue = current.length > 0;
        var libraryType = field.getAttribute('data-library');
        var settings = {
            title: multiple
                ? (hasValue
                    ? (field.getAttribute('data-add') || 'Add')
                    : (field.getAttribute('data-select') || 'Select'))
                : (hasValue
                    ? (field.getAttribute('data-replace') || 'Replace')
                    : (field.getAttribute('data-select') || 'Select')),
            button: {
                text: field.getAttribute('data-select') || 'Select'
            },
            multiple: multiple
        };
        var frame;

        if (libraryType) {
            settings.library = { type: libraryType };
        }

        frame = wp.media(settings);

        frame.on('open', function () {
            var selection;

            if (multiple || !hasValue) {
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

        field = target.closest('.wpb-image-field, .wpb-file-field');

        if (!field) {
            return;
        }

        button = target.closest('button');

        if (button && field.contains(button) && button.classList.contains(isFile(field) ? 'wpb-file-field__remove' : 'wpb-image-field__remove')) {
            event.preventDefault();

            if (isMultiple(field)) {
                item = button.closest(part(field, 'item'));

                if (item) {
                    item.remove();
                }

                updateButton(field);
                return;
            }

            paint(field, null);
            return;
        }

        if (button && button.classList.contains(isFile(field) ? 'wpb-file-field__select' : 'wpb-image-field__select')) {
            event.preventDefault();

            if (window.wp && wp.media) {
                openFrame(field);
            }

            return;
        }

        if (!isFile(field) && target.closest('.wpb-image-field__preview img') && window.wp && wp.media) {
            event.preventDefault();
            openFrame(field);
        }
    });
}());
