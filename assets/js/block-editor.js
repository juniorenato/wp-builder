/**
 * Inline editing for block fields stored in post meta or in a repeater.
 *
 * The canvas is the editor. The public render stays on the server.
 *
 * WordPress provides wp.element, wp.blockEditor, wp.data, and wp.hooks.
 */
(function () {
    function boot() {
        if (window.wpbBlockEditorBound) {
            return true;
        }

        if (!window.wp || !wp.hooks || !wp.element || !wp.components || !wp.blockEditor || !wp.data) {
            return false;
        }

        window.wpbBlockEditorBound = true;
        bindFields();

        return true;
    }

    function bindFields() {
        var config = window.wpbBlockEditor || { i18n: {}, blocks: {} };
        var el = wp.element.createElement;
        var PlainText = wp.blockEditor.PlainText;
        var MediaUpload = wp.blockEditor.MediaUpload;
        var MediaUploadCheck = wp.blockEditor.MediaUploadCheck;
        var useBlockProps = wp.blockEditor.useBlockProps;
        var useSelect = wp.data.useSelect;
        var Button = wp.components.Button;
        var i18n = config.i18n || {};

        function fieldClass(name) {
            return String(name).replace(/[^A-Za-z0-9_-]/g, '');
        }

        function stop(event) {
            event.stopPropagation();
        }

        function defaultItem(fields) {
            var item = {};

            fields.forEach(function (field) {
                item[field.name] = field.type === 'repeater' ? [] : field.default;
            });

            return item;
        }

        function ImageField(props) {
            var field = props.field;
            var media = useSelect(function (select) {
                if (!props.value) {
                    return null;
                }

                return select('core').getMedia(props.value);
            }, [props.value]);
            var className = 'wp-block-image is-layout-flow wpb-field wpb-field--' + fieldClass(field.name) + ' wpb-field--image components-placeholder__fieldset';

            return el(MediaUploadCheck, null,
                el(MediaUpload, {
                    onSelect: function (selected) {
                        props.onChange(selected && selected.id ? selected.id : 0);
                    },
                    allowedTypes: ['image'],
                    value: props.value || 0,
                    render: function (mediaProps) {
                        return el('figure', { className: className },
                            media && media.source_url
                                ? el('img', { src: media.source_url, alt: '', onClick: mediaProps.open })
                                : el('div', {
                                    className: 'components-placeholder',
                                    onClick: mediaProps.open
                                }, el('div', { className: 'components-placeholder__label' }, field.label)),
                            props.value
                                ? el(Button, {
                                    variant: 'secondary',
                                    __next40pxDefaultSize: true,
                                    onMouseDown: stop,
                                    onClick: function (event) {
                                        stop(event);
                                        props.onChange(0);
                                    }
                                }, i18n.removeImage)
                                : null
                        );
                    }
                })
            );
        }

        function InlineField(props) {
            var field = props.field;
            var value = props.value;
            var className = 'wpb-field wpb-field--' + fieldClass(field.name) + ' wpb-field--' + field.type;

            if (field.type === 'boolean') {
                return null;
            }

            if (field.type === 'enum') {
                return el('select', {
                    className: 'components-select-control__input ' + className,
                    value: value || field.default || '',
                    'aria-label': field.label,
                    onMouseDown: stop,
                    onChange: function (event) {
                        props.onChange(event.target.value);
                    }
                }, (field.enum || []).map(function (option) {
                    return el('option', { key: option, value: option }, option);
                }));
            }

            if (field.type === 'integer' || field.type === 'number') {
                return el(PlainText, {
                    className: className,
                    value: value === 0 || value ? String(value) : '',
                    placeholder: field.label,
                    onChange: function (next) {
                        if (next === '') {
                            props.onChange(field.default);
                            return;
                        }

                        var parsed = field.type === 'integer' ? parseInt(next, 10) : parseFloat(next);
                        props.onChange(Number.isFinite(parsed) ? parsed : field.default);
                    }
                });
            }

            if (field.type === 'image') {
                return el(ImageField, {
                    field: field,
                    value: value || 0,
                    onChange: props.onChange
                });
            }

            return el(PlainText, {
                className: className,
                value: value || '',
                placeholder: field.label,
                onChange: props.onChange
            });
        }

        function RepeaterList(props) {
            var field = props.field;
            var items = Array.isArray(props.value) ? props.value : [];
            var atMax = field.max > 0 && items.length >= field.max;
            var atMin = items.length <= field.min;

            function change(next) {
                props.onChange(next);
            }

            function updateItem(index, name, value) {
                change(items.map(function (item, itemIndex) {
                    if (itemIndex !== index) {
                        return item;
                    }

                    var copy = Object.assign({}, item);
                    copy[name] = value;
                    return copy;
                }));
            }

            function move(index, direction) {
                var target = index + direction;

                if (target < 0 || target >= items.length) {
                    return;
                }

                var next = items.slice();
                var swapped = next[target];
                next[target] = next[index];
                next[index] = swapped;
                change(next);
            }

            var rows = items.map(function (item, index) {
                var classes = ['is-layout-flow', 'wpb-repeater__item'];

                field.fields.forEach(function (child) {
                    if (child.type === 'boolean' && item[child.name]) {
                        classes.push('wpb-repeater__item--' + fieldClass(child.name));
                    }
                });

                var toggles = field.fields.filter(function (child) {
                    return child.type === 'boolean';
                }).map(function (child) {
                    return el(Button, {
                        key: child.name,
                        variant: 'secondary',
                        isPressed: !!item[child.name],
                        __next40pxDefaultSize: true,
                        onMouseDown: stop,
                        onClick: function (event) {
                            stop(event);
                            updateItem(index, child.name, !item[child.name]);
                        }
                    }, child.label);
                });

                var fields = field.fields.map(function (child) {
                    if (child.type === 'repeater') {
                        return el(RepeaterList, {
                            key: child.name,
                            field: child,
                            value: item[child.name],
                            onChange: function (next) {
                                updateItem(index, child.name, next);
                            }
                        });
                    }

                    return el(InlineField, {
                        key: child.name,
                        field: child,
                        value: item[child.name],
                        onChange: function (next) {
                            updateItem(index, child.name, next);
                        }
                    });
                });

                return el('div', { key: index, className: classes.join(' ') },
                    fields,
                    el('div', { className: 'components-placeholder__fieldset' },
                        el(Button, {
                            variant: 'secondary',
                            __next40pxDefaultSize: true,
                            disabled: index === 0,
                            onMouseDown: stop,
                            onClick: function (event) {
                                stop(event);
                                move(index, -1);
                            }
                        }, i18n.moveUp),
                        el(Button, {
                            variant: 'secondary',
                            __next40pxDefaultSize: true,
                            disabled: index === items.length - 1,
                            onMouseDown: stop,
                            onClick: function (event) {
                                stop(event);
                                move(index, 1);
                            }
                        }, i18n.moveDown),
                        toggles,
                        el(Button, {
                            variant: 'secondary',
                            __next40pxDefaultSize: true,
                            disabled: atMin,
                            onMouseDown: stop,
                            onClick: function (event) {
                                stop(event);
                                change(items.filter(function (_, itemIndex) {
                                    return itemIndex !== index;
                                }));
                            }
                        }, i18n.remove)
                    )
                );
            });

            return el('div', { className: 'is-layout-flow wpb-repeater wpb-repeater--' + fieldClass(field.name) },
                rows,
                el('div', { className: 'components-placeholder__fieldset' },
                el(Button, {
                    variant: 'primary',
                    className: 'wpb-repeater__add',
                    disabled: atMax,
                    __next40pxDefaultSize: true,
                    onMouseDown: stop,
                    onClick: function (event) {
                        stop(event);

                        if (atMax) {
                            return;
                        }

                        change(items.concat([defaultItem(field.fields)]));
                    }
                }, i18n.add + ' ' + field.label)
            ));
        }

        function instanceAttribute(blockConfig) {
            return blockConfig.instance || 'wpbId';
        }

        function isInstanceId(value) {
            return /^[0-9a-f]{8}-[0-9a-f]{4}-[1-8][0-9a-f]{3}-[89ab][0-9a-f]{3}-[0-9a-f]{12}$/i.test(String(value || ''));
        }

        function isPlainObject(value) {
            return !!value && typeof value === 'object' && !Array.isArray(value);
        }

        function isInstanceMap(value) {
            if (!isPlainObject(value)) {
                return false;
            }

            return Object.keys(value).every(function (key) {
                var bag = value[key];

                return isInstanceId(key) && (isPlainObject(bag) || (Array.isArray(bag) && bag.length === 0));
            });
        }

        function cloneValue(value) {
            return JSON.parse(JSON.stringify(value));
        }

        function withDefaults(fields, source) {
            var bag = Object.assign({}, source || {});

            (fields || []).forEach(function (field) {
                if (!Object.prototype.hasOwnProperty.call(bag, field.name)) {
                    bag[field.name] = field.type === 'repeater' ? [] : field.default;
                }
            });

            return bag;
        }

        function createId() {
            if (window.crypto && typeof crypto.randomUUID === 'function') {
                return crypto.randomUUID();
            }

            return 'xxxxxxxx-xxxx-4xxx-yxxx-xxxxxxxxxxxx'.replace(/[xy]/g, function (symbol) {
                var random = Math.random() * 16 | 0;
                var next = symbol === 'x' ? random : (random & 0x3 | 0x8);

                return next.toString(16);
            });
        }

        function idIsShared(name, attribute, id, clientId) {
            if (!isInstanceId(id)) {
                return false;
            }

            var select = wp.data.select('core/block-editor');

            if (!select || !select.getBlocks) {
                return false;
            }

            return collectBlocks(select.getBlocks(), name, []).some(function (block) {
                if (block.clientId === clientId || !block.attributes) {
                    return false;
                }

                return block.attributes[attribute] === id;
            });
        }

        function collectBlocks(blocks, name, found) {
            (blocks || []).forEach(function (block) {
                if (block.name === name) {
                    found.push(block);
                }

                if (block.innerBlocks && block.innerBlocks.length) {
                    collectBlocks(block.innerBlocks, name, found);
                }
            });

            return found;
        }

        var lastSignature = null;
        var reconciling = false;

        function reconcileMetaBlocks() {
            if (reconciling) {
                return;
            }

            var blockSelect = wp.data.select('core/block-editor');
            var editorSelect = wp.data.select('core/editor');

            if (!blockSelect || !blockSelect.getBlocks || !editorSelect || !editorSelect.getEditedPostAttribute) {
                return;
            }

            var tree = blockSelect.getBlocks();
            var parts = [];

            Object.keys(config.blocks || {}).forEach(function (name) {
                var blockConfig = config.blocks[name];

                if (!blockConfig || blockConfig.storage !== 'meta') {
                    return;
                }

                collectBlocks(tree, name, []).forEach(function (block) {
                    var id = block.attributes && block.attributes[instanceAttribute(blockConfig)];
                    parts.push(block.clientId + ':' + (id || ''));
                });
            });

            var signature = parts.join('|');

            if (signature === lastSignature) {
                return;
            }

            lastSignature = signature;
            reconciling = true;

            try {
                Object.keys(config.blocks || {}).forEach(function (name) {
                    var blockConfig = config.blocks[name];

                    if (!blockConfig || blockConfig.storage !== 'meta') {
                        return;
                    }

                    reconcileBlock(name, blockConfig);
                });
            } finally {
                reconciling = false;
            }
        }

        function reconcileBlock(name, blockConfig) {
            var attribute = instanceAttribute(blockConfig);
            var blocks = collectBlocks(wp.data.select('core/block-editor').getBlocks(), name, []);

            if (!blocks.length) {
                return;
            }

            var meta = wp.data.select('core/editor').getEditedPostAttribute('meta') || {};
            var stored = meta[blockConfig.attribute];
            var legacy = isPlainObject(stored) && !isInstanceMap(stored);
            var map = isInstanceMap(stored) ? Object.assign({}, stored) : {};
            var seen = {};
            var dirtyMeta = legacy;

            blocks.forEach(function (block) {
                var id = block.attributes && block.attributes[attribute] || '';

                if (isInstanceId(id) && !seen[id]) {
                    seen[id] = true;

                    if (legacy) {
                        map[id] = cloneValue(stored);
                    }

                    return;
                }

                var newId = createId();
                var source = isInstanceId(id) && isPlainObject(map[id]) ? map[id] : (legacy ? stored : null);

                wp.data.dispatch('core/block-editor').updateBlockAttributes(block.clientId, {
                    [attribute]: newId
                });

                if (source) {
                    map[newId] = cloneValue(source);
                    dirtyMeta = true;
                }

                seen[newId] = true;
            });

            if (!dirtyMeta) {
                return;
            }

            var editor = wp.data.dispatch('core/editor');

            if (!editor || !editor.editPost) {
                return;
            }

            editor.editPost({
                meta: { [blockConfig.attribute]: map }
            });
        }

        function BlockCanvas(props) {
            var blockConfig = props.config;
            var attributeName = instanceAttribute(blockConfig);
            var instanceId = props.attributes[attributeName] || '';
            var blockProps = useBlockProps();
            var metaBag = useSelect(function (select) {
                if (blockConfig.storage !== 'meta') {
                    return null;
                }

                var editor = select('core/editor');

                if (!editor || !editor.getEditedPostAttribute) {
                    return null;
                }

                var meta = editor.getEditedPostAttribute('meta') || {};
                var stored = meta[blockConfig.attribute];

                if (!stored || typeof stored !== 'object') {
                    return null;
                }

                if (!isInstanceMap(stored)) {
                    return stored;
                }

                return instanceId && stored[instanceId] ? stored[instanceId] : null;
            }, [blockConfig.storage, blockConfig.attribute, instanceId]);
            var bag = blockConfig.storage === 'meta'
                ? withDefaults(blockConfig.fields, metaBag)
                : props.attributes;

            function commitBag(next) {
                var id = instanceId;
                var editorSelect = wp.data.select('core/editor');
                var edited = editorSelect && editorSelect.getEditedPostAttribute
                    ? (editorSelect.getEditedPostAttribute('meta') || {})
                    : {};
                var storedNow = edited[blockConfig.attribute];

                if (!isInstanceId(id) || idIsShared(props.name, attributeName, id, props.clientId) || (isPlainObject(storedNow) && !isInstanceMap(storedNow))) {
                    reconcileMetaBlocks();
                    var current = wp.data.select('core/block-editor').getBlock(props.clientId);
                    id = current && current.attributes ? current.attributes[attributeName] : '';
                }

                if (!isInstanceId(id)) {
                    return;
                }

                var metaSelect = wp.data.select('core/editor');
                var meta = metaSelect && metaSelect.getEditedPostAttribute
                    ? (metaSelect.getEditedPostAttribute('meta') || {})
                    : {};
                var stored = meta[blockConfig.attribute];
                var map = isInstanceMap(stored) ? Object.assign({}, stored) : {};
                map[id] = next;

                var editor = wp.data.dispatch('core/editor');

                if (editor && editor.editPost) {
                    editor.editPost({
                        meta: { [blockConfig.attribute]: map }
                    });
                }
            }

            function changeField(name, value) {
                if (blockConfig.storage === 'meta') {
                    var next = Object.assign({}, bag);
                    next[name] = value;
                    commitBag(next);
                    return;
                }

                props.setAttributes({ [name]: value });
            }

            blockProps.className = (blockProps.className ? blockProps.className + ' ' : '') + 'wpb-block';

            return el('div', blockProps, blockConfig.fields.map(function (field) {
                if (field.type === 'repeater') {
                    return el(RepeaterList, {
                        key: field.name,
                        field: field,
                        value: bag[field.name],
                        onChange: function (next) {
                            changeField(field.name, next);
                        }
                    });
                }

                return el(InlineField, {
                    key: field.name,
                    field: field,
                    value: bag[field.name],
                    onChange: function (next) {
                        changeField(field.name, next);
                    }
                });
            }));
        }

        function withBlockFields(BlockEdit) {
            return function (props) {
                var blockConfig = config.blocks && config.blocks[props.name];

                if (!blockConfig) {
                    return el(BlockEdit, props);
                }

                return el(BlockCanvas, {
                    config: blockConfig,
                    name: props.name,
                    clientId: props.clientId,
                    attributes: props.attributes,
                    setAttributes: props.setAttributes
                });
            };
        }

        wp.hooks.addFilter('editor.BlockEdit', 'wpb/fields', withBlockFields);

        if (wp.data.subscribe) {
            wp.data.subscribe(reconcileMetaBlocks);
            reconcileMetaBlocks();
        }
    }

    if (!boot()) {
        document.addEventListener('DOMContentLoaded', boot);
        var attempts = 0;
        var timer = setInterval(function () {
            attempts += 1;

            if (boot() || attempts > 100) {
                clearInterval(timer);
            }
        }, 50);
    }
}());
