(function() {
    const { registerBlockType } = wp.blocks;
    const { RichText, useBlockProps } = wp.blockEditor;

    registerBlockType('meupl/texto-simples', {
        title: 'Texto simples',
        icon: 'admin-post',
        category: 'widgets',
        attributes: {
            conteudo: {
                type: 'string',
                source: 'meta',
                meta: 'texto_simples',
            },
        },
        edit: ({ attributes, setAttributes }) => {
            const blockProps = useBlockProps();
            return wp.element.createElement(RichText, {
                ...blockProps,
                tagName: 'p',
                value: attributes.conteudo,
                onChange: (val) => setAttributes({ conteudo: val }),
                placeholder: 'Digite algo...',
            });
        },
        save: () => null, // Renderizado via PHP
    });
})();