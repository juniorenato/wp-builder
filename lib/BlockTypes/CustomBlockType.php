<?php

namespace WPB\BlockTypes;

class CustomBlockType
{
    protected string $slug;
    protected string $title;
    protected string $json_path;
    protected string $block_name;
    protected array $fields;

    public function __construct(string $slug, string $title, array $fields)
    {
        $this->slug = $slug;
        $this->title = $title;
        $this->fields = $fields;
        $this->block_name = "wpb/{$slug}";

        $this->json_path = get_template_directory() . "/wp/blocks/{$slug}.json";

        add_action('init', [$this, 'register_block']);
        add_action('rest_api_init', [$this, 'register_meta']);
        add_action('enqueue_block_editor_assets', [$this, 'enqueue_block_editor_assets']);
    }

    public function register_block(): void
    {
        if (!file_exists($this->json_path)) {
            $this->generate_block_json();
        }

        register_block_type_from_metadata($this->json_path, [
            'render_callback' => [$this, 'render']
        ]);
    }

    public function register_meta(): void
    {
        foreach($this->fields as $key => $field) {
            register_post_meta('', $key, [
                'show_in_rest' => true,
                'single'       => true,
                'type'         => 'string'
            ]);
        }
    }

    protected function generate_block_json(): void
    {
        $attributes = [];
        foreach($this->fields as $key => $field) {
            $attributes[$key] = [
                'html_type' => $field['type'],
                'type'   => 'string',
                'source' => 'meta',
                'meta'   => $key
            ];

            if(isset($field['options'])) {
                $attributes[$key]['options'] = $field['options'];
            }
        }

        $data = [
            '$schema'      => 'https://schemas.wp.org/trunk/block.json',
            'apiVersion'   => 2,
            'name'         => $this->block_name,
            'title'        => $this->title,
            'category'     => 'common',
            'icon'         => 'admin-generic',
            'editorScript' => 'wpb-generic-block-loader',
            'attributes'   => $attributes,
            'supports'     => ['html' => false]
        ];

        if (!is_dir(dirname($this->json_path))) {
            mkdir(dirname($this->json_path), 0755, true);
        }

        file_put_contents(
            $this->json_path,
            json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES)
        );
    }

    public function render(array $attributes, string $content): string
    {
        return "<div class=\"wpb-block wpb-block-{$this->slug}\"></div>";
    }

    public function enqueue_block_editor_assets() {
        wp_register_script(
            'wpb-generic-block-loader',
            get_template_directory_uri() . '/dist/scripts/block-loader.js',
            ['wp-blocks', 'wp-element', 'wp-components', 'wp-block-editor'],
            filemtime(get_template_directory() . '/dist/scripts/block-loader.js'),
            true
        );

        wp_localize_script('wpb-generic-block-loader', 'WPB_BLOCKS', [
            'blocks' => array_map(function ($file) {
                return json_decode(file_get_contents($file), true);
            }, glob(get_template_directory() . '/wp/blocks/*.json'))
        ]);

        wp_enqueue_script('wpb-generic-block-loader');
    }
}
