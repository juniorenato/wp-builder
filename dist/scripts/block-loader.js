(function (blocks, element, components, blockEditor) {
	const { registerBlockType } = blocks;
	const { createElement: el, Fragment } = element;
	const { TextControl, CheckboxControl, SelectControl, PanelBody } = components;
	const { InspectorControls } = blockEditor;

	function getFieldComponent(key, attr, value, onChange) {

        console.log(attr.options);

		if (attr.html_type === 'boolean') {
			return el(CheckboxControl, {
				label: key,
				checked: value,
				onChange: (val) => onChange({ [key]: val }),
			});
		}

		if (attr.html_type === 'string' && attr.options) {
			return el(SelectControl, {
				label: key,
				value: value,
				options: attr.options.map(opt => ({ label: opt.label, value: opt.value })),
				onChange: (val) => onChange({ [key]: val }),
			});
		}

		if (attr.html_type === 'select' && attr.options) {
			return el(SelectControl, {
				label: key,
				value: value,
				options: attr.options.map(opt => ({ label: opt.label, value: opt.value })),
				onChange: (val) => onChange({ [key]: val }),
			});
		}

		// padrão: string => TextControl
		return el(TextControl, {
			label: key,
			value: value,
			onChange: (val) => onChange({ [key]: val }),
		});
	}

	WPB_BLOCKS.blocks.forEach((block) => {
		const { name, title, icon, category, attributes } = block;

		registerBlockType(name, {
			title,
			icon,
			category,
			attributes,
			edit: (props) => {
				const { attributes: values, setAttributes } = props;

				const fields = Object.entries(attributes).map(([key, attr]) =>
					el('div', { key },
						getFieldComponent(key, attr, values[key], setAttributes)
					)
				);

				return el(
					Fragment,
					{},
					el(
						InspectorControls,
						{},
						el(PanelBody, { title: 'Configurações', initialOpen: true }, fields)
					),
					el('div', { className: 'wpb-block-form' }, fields)
				);
			},
			save: () => null // renderizado via PHP
		});
	});
})(
	window.wp.blocks,
	window.wp.element,
	window.wp.components,
	window.wp.blockEditor
);
