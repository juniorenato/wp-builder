/**
 * Adds, removes, and reorders repeater rows on meta boxes, term screens, and admin pages.
 *
 * Row markup comes from the server. This script clones the inert template and rewrites
 * request names so each row posts as a dense list.
 */
(function () {
    if (window.wpbAdminRepeaterBound) {
        return;
    }

    window.wpbAdminRepeaterBound = true;

    function childByClass(parent, className) {
        var children = parent.children;

        for (var i = 0; i < children.length; i++) {
            if (children[i].classList.contains(className)) {
                return children[i];
            }
        }

        return null;
    }

    function childrenByClass(parent, className) {
        var found = [];
        var children = parent.children;

        for (var i = 0; i < children.length; i++) {
            if (children[i].classList.contains(className)) {
                found.push(children[i]);
            }
        }

        return found;
    }

    function itemsOf(repeater) {
        var list = childByClass(repeater, 'wpb-repeater__items');

        return list ? childrenByClass(list, 'wpb-repeater__item') : [];
    }

    function outermost(repeater) {
        var current = repeater;

        while (current.parentElement && current.parentElement.closest('.wpb-repeater')) {
            current = current.parentElement.closest('.wpb-repeater');
        }

        return current;
    }

    function belongsTo(element, repeater) {
        return element.closest('.wpb-repeater') === repeater;
    }

    function reindex(repeater, prefix) {
        var leaf = repeater.getAttribute('data-name') || '';
        var base = prefix === '' ? leaf : prefix + '[' + leaf + ']';
        var items = itemsOf(repeater);
        var min = Number(repeater.getAttribute('data-min')) || 0;
        var max = Number(repeater.getAttribute('data-max')) || 0;
        var marker = childByClass(repeater, 'wpb-repeater__present');
        var footer = childByClass(repeater, 'wpb-repeater__footer');
        var add = footer ? footer.getElementsByClassName('wpb-repeater__add')[0] : null;

        if (marker) {
            marker.name = base + '[__present]';
        }

        items.forEach(function (item, index) {
            var path = base + '[' + index + ']';
            var fields = item.getElementsByClassName('wpb-repeater__field');
            var toolbar = childByClass(item, 'wpb-repeater__toolbar');
            var nested = item.getElementsByClassName('wpb-repeater');

            Array.prototype.forEach.call(fields, function (field) {
                var controls;
                var labelled;
                var label;
                var description;
                var key;
                var multiple;
                var name;
                var id;

                if (!belongsTo(field, repeater)) {
                    return;
                }

                key = field.getAttribute('data-key') || '';
                multiple = field.getAttribute('data-multiple') === '1';
                name = path + '[' + key + ']' + (multiple ? '[]' : '');
                id = 'field_' + path.replace(/[^A-Za-z0-9_-]+/g, '_') + '_' + key;
                controls = field.querySelectorAll('input, select, textarea');

                Array.prototype.forEach.call(controls, function (control) {
                    if (!belongsTo(control, repeater)) {
                        return;
                    }

                    control.name = name;

                    if (control.getAttribute('aria-describedby')) {
                        control.setAttribute('aria-describedby', name + '-description');
                    }
                });

                labelled = Array.prototype.find.call(field.querySelectorAll('[id]'), function (element) {
                    return belongsTo(element, repeater);
                });

                if (labelled) {
                    labelled.id = id;
                }

                label = Array.prototype.find.call(field.querySelectorAll('label[for]'), function (element) {
                    return belongsTo(element, repeater);
                });

                if (label) {
                    label.htmlFor = id;
                }

                description = Array.prototype.find.call(field.querySelectorAll('.description'), function (element) {
                    return belongsTo(element, repeater);
                });

                if (description) {
                    description.id = name + '-description';
                }

                Array.prototype.forEach.call(field.querySelectorAll('[aria-describedby]'), function (element) {
                    if (!belongsTo(element, repeater)) {
                        return;
                    }

                    element.setAttribute('aria-describedby', name + '-description');
                });
            });

            Array.prototype.forEach.call(nested, function (child) {
                if (child.parentElement && child.parentElement.closest('.wpb-repeater') === repeater) {
                    reindex(child, path);
                }
            });

            if (toolbar) {
                var up = toolbar.getElementsByClassName('wpb-repeater__up')[0];
                var down = toolbar.getElementsByClassName('wpb-repeater__down')[0];
                var remove = toolbar.getElementsByClassName('wpb-repeater__remove')[0];

                if (up) {
                    up.disabled = index === 0;
                }

                if (down) {
                    down.disabled = index === items.length - 1;
                }

                if (remove) {
                    remove.disabled = items.length <= min;
                }
            }
        });

        if (add) {
            add.disabled = max > 0 && items.length >= max;
        }
    }

    function refresh(repeater) {
        reindex(outermost(repeater), '');
    }

    function directTemplate(repeater) {
        var children = repeater.children;

        for (var i = 0; i < children.length; i++) {
            if (children[i].tagName === 'TEMPLATE') {
                return children[i];
            }
        }

        return null;
    }

    function appendFromTemplate(repeater) {
        var max = Number(repeater.getAttribute('data-max')) || 0;
        var list = childByClass(repeater, 'wpb-repeater__items');
        var template = directTemplate(repeater);
        var fragment;

        if (!list || !template) {
            return;
        }

        if (max > 0 && itemsOf(repeater).length >= max) {
            return;
        }

        fragment = template.content.cloneNode(true);
        list.appendChild(fragment);
    }

    function moveItem(item, direction) {
        var sibling = direction < 0 ? item.previousElementSibling : item.nextElementSibling;

        if (!sibling || !sibling.classList.contains('wpb-repeater__item') || !item.parentElement) {
            return;
        }

        if (direction < 0) {
            item.parentElement.insertBefore(item, sibling);
            return;
        }

        item.parentElement.insertBefore(sibling, item);
    }

    document.addEventListener('click', function (event) {
        var target = event.target;
        var button;
        var repeater;
        var item;

        if (!target || !target.closest) {
            return;
        }

        button = target.closest('button');

        if (!button) {
            return;
        }

        repeater = button.closest('.wpb-repeater');

        if (!repeater) {
            return;
        }

        if (button.classList.contains('wpb-repeater__add')) {
            event.preventDefault();
            appendFromTemplate(repeater);
            refresh(repeater);
            return;
        }

        item = button.closest('.wpb-repeater__item');

        if (!item || item.closest('.wpb-repeater') !== repeater) {
            return;
        }

        if (button.classList.contains('wpb-repeater__remove')) {
            event.preventDefault();

            if (button.disabled) {
                return;
            }

            item.remove();
            refresh(repeater);
            return;
        }

        if (button.classList.contains('wpb-repeater__up')) {
            event.preventDefault();
            moveItem(item, -1);
            refresh(repeater);
            return;
        }

        if (button.classList.contains('wpb-repeater__down')) {
            event.preventDefault();
            moveItem(item, 1);
            refresh(repeater);
        }
    });

    function boot() {
        document.querySelectorAll('.wpb-repeater').forEach(function (repeater) {
            if (!repeater.parentElement || !repeater.parentElement.closest('.wpb-repeater')) {
                reindex(repeater, '');
            }
        });
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', boot);
    } else {
        boot();
    }
}());
