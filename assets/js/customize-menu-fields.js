/**
 * Saves menu item fields from the Customizer.
 *
 * The menu item template is shared. Fields granted to a theme location are
 * hidden unless the item's menu is assigned to that location. A change creates
 * a setting WordPress can store with the changeset.
 */
(function () {
    if (!window.wp || !wp.customize) {
        return;
    }

    var data = window.wpbMenuFields || { fields: {}, values: {}, media: {} };
    var populating = false;

    wp.customize.bind('ready', function () {
        wp.customize.control.each(watch);
        wp.customize.control.bind('add', watch);
        wp.customize.each(function (setting) {
            if (String(setting.id).indexOf('nav_menu_locations[') === 0) {
                setting.bind(toggleAll);
            }
        });

        var previewer = wp.customize.previewer;
        var save = previewer.save;

        previewer.save = function () {
            document.querySelectorAll('.wpb-menu-field[data-wpb-edited="1"]').forEach(syncField);

            return save.apply(this, arguments);
        };
    });

    document.addEventListener('input', onEdit);
    document.addEventListener('change', onEdit);

    function watch(control) {
        if (!control.params || control.params.type !== 'nav_menu_item') {
            return;
        }

        var run = function () {
            prepare(control);
        };

        if (control.deferred && control.deferred.embedded) {
            control.deferred.embedded.done(run);
            return;
        }

        run();
    }

    function prepare(control) {
        var root = control.container && control.container[0];

        if (!root || root.getAttribute('data-wpb-ready') === '1') {
            return;
        }

        root.setAttribute('data-wpb-ready', '1');
        populate(root);
        toggle(root);
        control.setting.bind(function () {
            toggle(root);
        });
    }

    function onEdit(event) {
        var field = event.target && event.target.closest
            ? event.target.closest('.wpb-menu-field')
            : null;

        if (!field || field.hidden || populating) {
            return;
        }

        field.setAttribute('data-wpb-edited', '1');
        syncField(field);
    }

    function toggleAll() {
        document.querySelectorAll('.customize-control-nav_menu_item').forEach(toggle);
    }

    function toggle(root) {
        root.querySelectorAll('.wpb-menu-field').forEach(function (field) {
            var name = field.getAttribute('data-wpb-field');
            var itemId = itemIdFromField(field);

            field.hidden = !visible(name, itemId);
        });
    }

    function visible(name, itemId) {
        var field = data.fields ? data.fields[name] : null;

        if (!field) {
            return false;
        }

        if (!field.locations || !field.locations.length) {
            return true;
        }

        var menuId = itemMenuId(itemId);

        return field.locations.some(function (location) {
            var setting = wp.customize('nav_menu_locations[' + location + ']');

            return !!setting && parseInt(setting(), 10) === menuId;
        });
    }

    function populate(root) {
        populating = true;
        root.querySelectorAll('.wpb-menu-field').forEach(function (field) {
            var itemId = itemIdFromField(field);
            var name = field.getAttribute('data-wpb-field');
            var stored = data.values ? data.values[String(itemId)] : null;

            if (!stored || !Object.prototype.hasOwnProperty.call(stored, name)) {
                return;
            }

            fill(field, stored[name]);
        });
        populating = false;
    }

    function fill(field, value) {
        var select = field.querySelector('select');

        if (select) {
            var selected = Array.isArray(value) ? value.map(String) : [String(value)];

            Array.prototype.forEach.call(select.options, function (option) {
                option.selected = selected.indexOf(option.value) !== -1;
            });

            return;
        }

        var checks = field.querySelectorAll('input[type="checkbox"], input[type="radio"]');

        if (checks.length) {
            if (Array.isArray(value)) {
                var picked = value.map(String);

                Array.prototype.forEach.call(checks, function (input) {
                    input.checked = picked.indexOf(input.value) !== -1;
                });

                return;
            }

            if (checks.length === 1 && checks[0].type === 'checkbox') {
                checks[0].checked = value === '1' || value === 1 || value === true;

                return;
            }

            Array.prototype.forEach.call(checks, function (input) {
                input.checked = String(value) === input.value;
            });

            return;
        }

        var media = field.querySelector('.wpb-image-field, .wpb-file-field');

        if (media) {
            fillMedia(media, value);

            return;
        }

        var box = field.querySelector('input[type="text"], input[type="number"], input[type="hidden"], textarea');

        if (box && !Array.isArray(value)) {
            box.value = value === null || value === undefined ? '' : String(value);
        }
    }

    function fillMedia(media, value) {
        var multiple = media.getAttribute('data-multiple') === '1';
        var file = media.classList.contains('wpb-file-field');
        var idClass = file ? 'wpb-file-field__id' : 'wpb-image-field__id';
        var preview = media.querySelector(file ? '.wpb-file-field__preview' : '.wpb-image-field__preview');
        var ids = multiple ? (Array.isArray(value) ? value : []) : [value];

        if (multiple) {
            if (!preview) {
                return;
            }

            preview.replaceChildren();
            ids.forEach(function (id) {
                if (!id) {
                    return;
                }

                var input = document.createElement('input');

                input.type = 'hidden';
                input.className = idClass;
                input.name = media.getAttribute('data-name') || '';
                input.value = String(id);
                preview.appendChild(input);
            });

            return;
        }

        var single = media.querySelector('.' + idClass);
        var id = ids.length && ids[0] ? String(ids[0]) : '';
        var info = data.media ? data.media[id] : null;

        if (single) {
            single.value = id;
        }

        if (preview && info && info.url) {
            preview.replaceChildren();

            if (file) {
                var link = document.createElement('a');

                link.className = 'wpb-file-field__name';
                link.href = info.url;
                link.target = '_blank';
                link.rel = 'noopener noreferrer';
                link.textContent = info.name || info.url;
                preview.appendChild(link);
            } else {
                var image = document.createElement('img');

                image.src = info.url;
                image.alt = info.alt || '';
                preview.appendChild(image);
            }
        }

        var remove = media.querySelector(file ? '.wpb-file-field__actions .wpb-file-field__remove' : '.wpb-image-field__actions .wpb-image-field__remove');

        if (remove) {
            remove.hidden = id === '';
        }
    }

    function syncField(field) {
        if (field.hidden) {
            return;
        }

        var itemId = itemIdFromField(field);
        var name = field.getAttribute('data-wpb-field');

        if (itemId === null || !name || !visible(name, itemId)) {
            return;
        }

        var value = readValue(field);
        var id = 'wpb_menu_item[' + itemId + '][' + name + ']';
        var setting = wp.customize(id);

        if (!setting) {
            setting = wp.customize.create(id, id, null, {
                type: 'wpb_menu_item',
                transport: 'refresh',
                previewer: wp.customize.previewer
            });
        }

        if (!same(setting(), value)) {
            setting.set(value);
        }
    }

    function readValue(field) {
        var select = field.querySelector('select');

        if (select) {
            if (!select.multiple) {
                return select.value;
            }

            return Array.prototype.map.call(select.selectedOptions, function (option) {
                return option.value;
            });
        }

        var checks = field.querySelectorAll('input[type="checkbox"], input[type="radio"]');

        if (checks.length) {
            var checked = Array.prototype.filter.call(checks, function (input) {
                return input.checked;
            });
            var multiple = checks[0].name.indexOf('[]') !== -1;

            if (multiple) {
                return checked.map(function (input) {
                    return input.value;
                });
            }

            return checked.length ? checked[checked.length - 1].value : '';
        }

        var media = field.querySelector('.wpb-image-field, .wpb-file-field');

        if (media && media.getAttribute('data-multiple') === '1') {
            return Array.prototype.map.call(media.querySelectorAll('input[type="hidden"]'), function (input) {
                return input.value;
            }).filter(Boolean);
        }

        var box = field.querySelector('input[type="text"], input[type="number"], input[type="hidden"], textarea');

        return box ? box.value : '';
    }

    function itemIdFromField(field) {
        var token = field.getAttribute('data-wpb-item');

        if (token) {
            var tokenId = parseInt(token, 10);

            if (!isNaN(tokenId)) {
                return tokenId;
            }
        }

        var name = field.getAttribute('data-wpb-field') || '';
        var input = field.querySelector('[name]');

        if (!input || name === '') {
            return null;
        }

        var request = input.name.replace(/\[\]$/, '');
        var prefix = 'wpb_menu_item_';
        var suffix = '_' + name;

        if (request.indexOf(prefix) !== 0 || request.slice(-suffix.length) !== suffix) {
            return null;
        }

        var id = parseInt(request.slice(prefix.length, request.length - suffix.length), 10);

        return isNaN(id) ? null : id;
    }

    function itemMenuId(itemId) {
        var setting = wp.customize('nav_menu_item[' + itemId + ']');
        var value = setting ? setting() : null;

        if (!value || value.nav_menu_term_id === undefined || value.nav_menu_term_id === null) {
            return 0;
        }

        return parseInt(value.nav_menu_term_id, 10) || 0;
    }

    function same(left, right) {
        return JSON.stringify(left) === JSON.stringify(right);
    }
}());
