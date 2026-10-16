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
        var useEffect = wp.element.useEffect;
        var useRef = wp.element.useRef;
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

        function BlockCanvas(props) {
            var blockConfig = props.config;
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

                return stored && typeof stored === 'object' ? stored : null;
            }, [blockConfig.storage, blockConfig.attribute]);
            var bag = blockConfig.storage === 'meta'
                ? (metaBag || props.attributes[blockConfig.attribute] || {})
                : props.attributes;
            var hydrated = useRef(false);

            useEffect(function () {
                if (hydrated.current || blockConfig.storage !== 'meta' || !metaBag) {
                    return;
                }

                hydrated.current = true;
                props.setAttributes({ [blockConfig.attribute]: metaBag });
            }, [metaBag]);

            function commitBag(next) {
                props.setAttributes({ [blockConfig.attribute]: next });

                var editor = wp.data.dispatch('core/editor');

                if (editor && editor.editPost) {
                    editor.editPost({
                        meta: { [blockConfig.attribute]: next }
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
                    attributes: props.attributes,
                    setAttributes: props.setAttributes
                });
            };
        }

        wp.hooks.addFilter('editor.BlockEdit', 'wpb/fields', withBlockFields);
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
