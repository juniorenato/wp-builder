/**
 * Copies repeater rows into a customizer setting.
 *
 * The admin repeater script owns add, remove, and reorder. This script reads
 * the visible rows and stores the array on the setting. Rows shown only to
 * meet a minimum are left unsaved until the user edits the list.
 */
(function () {
    if (!window.wp || !wp.customize || window.wpbCustomizeRepeaterBound) {
        return;
    }

    window.wpbCustomizeRepeaterBound = true;

    var suppress = false;

    function childByClass(parent, className) {
        var children = parent.children;
        var index;

        for (index = 0; index < children.length; index += 1) {
            if (children[index].classList.contains(className)) {
                return children[index];
            }
        }

        return null;
    }

    function rootRepeater(node) {
        var current;

        if (!node || !node.closest) {
            return null;
        }

        current = node.closest('.wpb-repeater');

        if (!current || !current.closest('.customize-control-wpb_repeater')) {
            return null;
        }

        while (current.parentElement && current.parentElement.closest('.wpb-repeater')) {
            current = current.parentElement.closest('.wpb-repeater');
        }

        return current;
    }

    function controlFor(repeater) {
        var container = repeater.closest('.customize-control');
        var id;

        if (!container || !container.id) {
            return null;
        }

        id = container.id.replace(/^customize-control-/, '');

        return wp.customize.control(id);
    }

    function readRepeater(repeater) {
        var list = childByClass(repeater, 'wpb-repeater__items');
        var rows = [];
        var index;

        if (!list) {
            return rows;
        }

        for (index = 0; index < list.children.length; index += 1) {
            if (list.children[index].classList.contains('wpb-repeater__item')) {
                rows.push(readItem(list.children[index], repeater));
            }
        }

        return rows;
    }

    function readItem(item, repeater) {
        var fields = childByClass(item, 'wpb-repeater__fields');
        var row = {};
        var index;
        var field;

        if (!fields) {
            return row;
        }

        for (index = 0; index < fields.children.length; index += 1) {
            field = fields.children[index];

            if (!field.classList.contains('wpb-repeater__field') || field.closest('.wpb-repeater') !== repeater) {
                continue;
            }

            row[field.getAttribute('data-key') || ''] = readField(field);
        }

        return row;
    }

    function readField(field) {
        var type = field.getAttribute('data-type') || 'text';
        var nested;
        var box;
        var checked;
        var hidden;
        var parsed;
        var color;
        var control;

        if (type === 'repeater') {
            nested = childByClass(field, 'wpb-repeater');

            if (!nested) {
                nested = field.querySelector('.wpb-repeater');
            }

            return nested ? readRepeater(nested) : [];
        }

        if (type === 'checkbox') {
            box = field.querySelector('input[type="checkbox"]');

            return !!(box && box.checked);
        }

        if (type === 'radio') {
            checked = field.querySelector('input[type="radio"]:checked');

            return checked ? checked.value : '';
        }

        if (type === 'image' || type === 'file') {
            hidden = field.querySelector(type === 'file' ? '.wpb-file-field__id' : '.wpb-image-field__id');
            parsed = hidden ? parseInt(hidden.value, 10) : 0;

            return parsed > 0 ? parsed : 0;
        }

        if (type === 'color') {
            color = field.querySelector('.wpb-color-field');

            return color ? color.value : '';
        }

        if (type === 'number') {
            control = field.querySelector('input');

            if (!control || control.value === '' || !isFinite(control.value)) {
                return '';
            }

            return control.value.indexOf('.') === -1 ? parseInt(control.value, 10) : parseFloat(control.value);
        }

        control = field.querySelector('input, select, textarea');

        return control ? control.value : '';
    }

    function sync(repeater) {
        var root = rootRepeater(repeater);
        var control;
        var value;
        var current;

        if (!root) {
            return;
        }

        control = controlFor(root);

        if (!control || !control.setting) {
            return;
        }

        value = readRepeater(root);
        current = control.setting.get();

        if (JSON.stringify(current) === JSON.stringify(value)) {
            return;
        }

        control.setting.set(value);
    }

    function initColors(scope) {
        var node = scope && scope.jquery ? scope[0] : scope;

        if (!node || !window.jQuery || !jQuery.fn.wpColorPicker) {
            return;
        }

        suppress = true;
        jQuery(node).find('.wpb-repeater__items .wpb-color-field').each(function () {
            var input = jQuery(this);

            if (input.hasClass('wp-color-picker') || input.attr('data-wpb-color-live') === '1') {
                return;
            }

            input.wpColorPicker({
                change: function () {
                    if (input.attr('data-wpb-color-live') === '1') {
                        input.trigger('wpb-color-change');
                    }
                },
                clear: function () {
                    input.val('');

                    if (input.attr('data-wpb-color-live') === '1') {
                        input.trigger('wpb-color-change');
                    }
                }
            });
        });
        suppress = false;
    }

    function onEdit(event) {
        var target = event.target;
        var repeater;

        if (suppress || !target || !target.closest || !target.closest('.wpb-repeater__items')) {
            return;
        }

        if (target.classList && target.classList.contains('wpb-color-field') && target.getAttribute('data-wpb-color-live') !== '1') {
            return;
        }

        repeater = rootRepeater(target);

        if (repeater) {
            sync(repeater);
        }
    }

    function sectionOf(control) {
        var sectionId = control.section && control.section.get ? control.section.get() : control.section;

        return sectionId ? wp.customize.section(sectionId) : null;
    }

    function watch(control) {
        var section;
        var paint = function () {
            var container = control.container && control.container[0] ? control.container[0] : control.container;

            initColors(container);
        };

        section = sectionOf(control);

        if (section && section.expanded && section.expanded.bind) {
            section.expanded.bind(function (expanded) {
                if (expanded) {
                    paint();
                }
            });

            if (section.expanded.get && section.expanded.get()) {
                paint();
            }

            return;
        }

        paint();
    }

    document.addEventListener('pointerdown', function (event) {
        var picker = event.target && event.target.closest ? event.target.closest('.wp-picker-container') : null;
        var input = picker ? picker.querySelector('.wpb-color-field') : null;

        if (input) {
            input.setAttribute('data-wpb-color-live', '1');
        }
    }, true);

    document.addEventListener('keydown', function (event) {
        if (event.target && event.target.classList && event.target.classList.contains('wpb-color-field')) {
            event.target.setAttribute('data-wpb-color-live', '1');
        }
    }, true);

    document.addEventListener('input', onEdit);
    document.addEventListener('change', onEdit);
    document.addEventListener('wpb-color-change', onEdit);
    document.addEventListener('wpb-repeater-change', function (event) {
        var repeater = event.target;

        if (!repeater || !repeater.classList || !repeater.classList.contains('wpb-repeater')) {
            return;
        }

        initColors(repeater);
        sync(repeater);
    });

    wp.customize.bind('ready', function () {
        wp.customize.control.each(function (control) {
            if (control.params && control.params.type === 'wpb_repeater') {
                watch(control);
            }
        });
    });
}());
