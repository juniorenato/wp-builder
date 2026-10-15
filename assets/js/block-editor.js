/**
 * Inspector controls for block fields stored in post meta or in a repeater.
 *
 * WordPress provides wp.element, wp.components, wp.blockEditor, wp.data, and wp.hooks.
 */
(function () {
    var config = window.wpbBlockEditor || { i18n: {}, blocks: {} };
    var el = wp.element.createElement;
    var Fragment = wp.element.Fragment;
    var useEffect = wp.element.useEffect;
    var useRef = wp.element.useRef;
    var PanelBody = wp.components.PanelBody;
    var TextControl = wp.components.TextControl;
    var ToggleControl = wp.components.ToggleControl;
    var SelectControl = wp.components.SelectControl;
    var Button = wp.components.Button;
    var BaseControl = wp.components.BaseControl;
    var InspectorControls = wp.blockEditor.InspectorControls;
    var MediaUpload = wp.blockEditor.MediaUpload;
    var MediaUploadCheck = wp.blockEditor.MediaUploadCheck;
    var useSelect = wp.data.useSelect;
    var i18n = config.i18n || {};

    function defaultItem(fields) {
        var item = {};

        fields.forEach(function (field) {
            item[field.name] = field.type === 'repeater' ? [] : field.default;
        });

        return item;
    }

    function itemLabel(item, fields, index, fallback) {
        var label = '';

        fields.some(function (field) {
            if ((field.type === 'string' || field.type === 'enum') && item[field.name]) {
                label = String(item[field.name]);
                return true;
            }

            return false;
        });

        return label || fallback + ' ' + (index + 1);
    }

    function ImageControl(props) {
        var media = useSelect(function (select) {
            if (!props.value) {
                return null;
            }

            return select('core').getMedia(props.value);
        }, [props.value]);

        var preview = media && media.source_url
            ? el('img', {
                src: media.source_url,
                alt: '',
                style: { display: 'block', maxWidth: '100%', height: 'auto', marginBottom: '8px' }
            })
            : null;

        return el(BaseControl, { label: props.field.label, __nextHasNoMarginBottom: true },
            preview,
            el(MediaUploadCheck, null,
                el(MediaUpload, {
                    onSelect: function (selected) {
                        props.onChange(selected && selected.id ? selected.id : 0);
                    },
                    allowedTypes: ['image'],
                    value: props.value || 0,
                    render: function (mediaProps) {
                        return el(Button, {
                            variant: 'secondary',
                            onClick: mediaProps.open
                        }, props.value ? i18n.replaceImage : i18n.selectImage);
                    }
                })
            ),
            props.value
                ? el(Button, {
                    variant: 'link',
                    isDestructive: true,
                    onClick: function () {
                        props.onChange(0);
                    }
                }, i18n.removeImage)
                : null
        );
    }

    function ScalarControl(props) {
        var field = props.field;
        var value = props.value;

        if (field.type === 'boolean') {
            return el(ToggleControl, {
                label: field.label,
                checked: !!value,
                onChange: props.onChange,
                __nextHasNoMarginBottom: true
            });
        }

        if (field.type === 'enum') {
            return el(SelectControl, {
                label: field.label,
                value: value || field.default,
                options: (field.enum || []).map(function (option) {
                    return { label: option, value: option };
                }),
                onChange: props.onChange,
                __nextHasNoMarginBottom: true
            });
        }

        if (field.type === 'integer' || field.type === 'number') {
            return el(TextControl, {
                label: field.label,
                type: 'number',
                step: field.type === 'integer' ? 1 : 'any',
                value: value === 0 || value ? String(value) : '',
                onChange: function (next) {
                    if (next === '') {
                        props.onChange(field.default);
                        return;
                    }

                    var parsed = field.type === 'integer' ? parseInt(next, 10) : parseFloat(next);
                    props.onChange(Number.isFinite(parsed) ? parsed : field.default);
                },
                __nextHasNoMarginBottom: true
            });
        }

        if (field.type === 'image') {
            return el(ImageControl, {
                field: field,
                value: value || 0,
                onChange: props.onChange
            });
        }

        return el(TextControl, {
            label: field.label,
            value: value || '',
            onChange: props.onChange,
            __nextHasNoMarginBottom: true
        });
    }

    function RepeaterControl(props) {
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

        var rows = items.map(function (item, index) {
            var controls = field.fields.map(function (child) {
                if (child.type === 'repeater') {
                    return el(RepeaterControl, {
                        key: child.name,
                        field: child,
                        value: item[child.name],
                        onChange: function (next) {
                            updateItem(index, child.name, next);
                        }
                    });
                }

                return el(ScalarControl, {
                    key: child.name,
                    field: child,
                    value: item[child.name],
                    onChange: function (next) {
                        updateItem(index, child.name, next);
                    }
                });
            });

            return el('div', {
                key: index,
                style: {
                    border: '1px solid #dcdcde',
                    padding: '12px',
                    marginBottom: '12px'
                }
            },
                el('div', {
                    style: {
                        display: 'flex',
                        justifyContent: 'space-between',
                        gap: '8px',
                        marginBottom: '8px'
                    }
                },
                    el('strong', null, itemLabel(item, field.fields, index, field.label)),
                    el('span', null,
                        el(Button, {
                            variant: 'tertiary',
                            disabled: index === 0,
                            onClick: function () {
                                var next = items.slice();
                                var swapped = next[index - 1];
                                next[index - 1] = next[index];
                                next[index] = swapped;
                                change(next);
                            }
                        }, i18n.moveUp),
                        el(Button, {
                            variant: 'tertiary',
                            disabled: index === items.length - 1,
                            onClick: function () {
                                var next = items.slice();
                                var swapped = next[index + 1];
                                next[index + 1] = next[index];
                                next[index] = swapped;
                                change(next);
                            }
                        }, i18n.moveDown),
                        el(Button, {
                            variant: 'tertiary',
                            isDestructive: true,
                            disabled: atMin,
                            onClick: function () {
                                change(items.filter(function (_, itemIndex) {
                                    return itemIndex !== index;
                                }));
                            }
                        }, i18n.remove)
                    )
                ),
                controls
            );
        });

        var body = el('div', { style: { marginTop: props.nested ? '12px' : 0 } },
            props.nested ? el('p', { style: { fontWeight: 600, marginBottom: '8px' } }, field.label) : null,
            rows,
            el(Button, {
                variant: 'secondary',
                disabled: atMax,
                onClick: function () {
                    if (atMax) {
                        return;
                    }

                    change(items.concat([defaultItem(field.fields)]));
                }
            }, i18n.add)
        );

        if (props.nested) {
            return body;
        }

        return el(PanelBody, { title: field.label, initialOpen: true }, body);
    }

    function FieldsPanel(props) {
        var blockConfig = props.config;
        var metaBag = useSelect(function (select) {
            if (blockConfig.storage !== 'meta') {
                return null;
            }

            var editor = select('core/editor');

            if (!editor || !editor.getEditedPostAttribute) {
                return null;
            }

            var meta = editor.getEditedPostAttribute('meta') || {};
            var value = meta[blockConfig.attribute];

            return value && typeof value === 'object' ? value : null;
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

        var controls = blockConfig.fields.map(function (field) {
            if (field.type === 'repeater') {
                return el(RepeaterControl, {
                    key: field.name,
                    field: field,
                    value: bag[field.name],
                    onChange: function (next) {
                        changeField(field.name, next);
                    }
                });
            }

            return el(ScalarControl, {
                key: field.name,
                field: field,
                value: bag[field.name],
                onChange: function (next) {
                    changeField(field.name, next);
                }
            });
        });

        return el(InspectorControls, null, controls);
    }

    function withBlockFields(BlockEdit) {
        return function (props) {
            var blockConfig = config.blocks && config.blocks[props.name];

            if (!blockConfig) {
                return el(BlockEdit, props);
            }

            return el(Fragment, null,
                el(BlockEdit, props),
                el(FieldsPanel, {
                    config: blockConfig,
                    attributes: props.attributes,
                    setAttributes: props.setAttributes
                })
            );
        };
    }

    wp.hooks.addFilter('editor.BlockEdit', 'wpb/fields', withBlockFields);
}());
