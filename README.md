# WordPress - Builder

First of all, if you are looking for a tool that gives you functions in the admin panel, you are in the wrong place!

This builder **was created for developers**. It aims to speed up and facilitate the development of WordPress.

See [Wiki](https://github.com/juniorenato/wp-builder/wiki#wordpress--builder) to know how it works.

## Wiki Index

1. [Installation](https://github.com/juniorenato/wp-builder/wiki/Installation)
    1. [Compsoer installation](https://github.com/juniorenato/wp-builder/wiki/Installation#composer-installation)
    1. [Manual installation](https://github.com/juniorenato/wp-builder/wiki/Installation#manual-installation)

2. [Fast mode](https://github.com/juniorenato/wp-builder/wiki/Fast-mode)
    1. [Custom Post Types](https://github.com/juniorenato/wp-builder/wiki/Fast-mode#custom-post-types)
    2. [Taxonomies](https://github.com/juniorenato/wp-builder/wiki/Fast-mode#taxonomies)
    2. [Pages](https://github.com/juniorenato/wp-builder/wiki/Fast-mode#pages)

3. [Post Types](https://github.com/juniorenato/wp-builder/wiki/Post-Types)
    1. [Creating a new custom post type](https://github.com/juniorenato/wp-builder/wiki/Post-Types#creating-a-new-custom-post-type)
    2. [Edit Post Type](https://github.com/juniorenato/wp-builder/wiki/Post-Types#edit-post-type)
    3. [Aditional helper methods](https://github.com/juniorenato/wp-builder/wiki/Post-Types#aditional-helper-methods)

4. [Gutenberg Blocks (PHP only)](#gutenberg-blocks-php-only)
    1. [Creating a block](#creating-a-block)
    2. [Limits](#limits)

5. [Version LOG](https://github.com/juniorenato/wp-builder/wiki/Version-LOG)

## Gutenberg Blocks (PHP only)

Requires **WordPress 7.0+**. Registers a Gutenberg block with PHP only (`autoRegister`): no JavaScript, no `block.json`, no build step.

### Creating a block

```php
use WPB\Blocks\Block;

$cta = new Block();
$cta->name('meu-tema/cta')
    ->title('Call to Action')
    ->description('Banner with title and button')
    ->category('widgets')
    ->icon('megaphone')
    ->addStringAttribute('heading', 'Heading', 'Get started')
    ->addEnumAttribute('size', 'Size', ['Small', 'Medium', 'Large'], 'Medium')
    ->supports([
        'align'   => ['wide', 'full'],
        'color'   => ['text' => true, 'background' => true],
        'spacing' => ['padding' => true, 'margin' => true],
    ])
    ->stylesheet(get_theme_file_uri('assets/cta.css'), 'meu-tema-cta')
    ->render(function (array $attributes): string {
        $wrapper = get_block_wrapper_attributes(['class' => 'meu-cta']);

        return sprintf(
            '<div %1$s><h2>%2$s</h2></div>',
            $wrapper,
            esc_html($attributes['heading'] ?? '')
        );
    });

$cta->register();
```

Constructor shortcut: `new Block('meu-tema/cta', 'Call to Action', $renderCallback)`.

Use `get_block_wrapper_attributes()` in the render callback so color, spacing, and border supports print on the wrapper. `stylesheet()` loads CSS on the front end and in the editor (PHP-only blocks skip `wp_enqueue_block_style()` in Gutenberg).

### Limits

Inspector controls are generated only for `string`, `integer`, `number`, `boolean`, and `string` + `enum`. There are no inner blocks, no static `save`, and no media/textarea/rich-text fields. Attribute names reserved by block supports (`style`, `className`, `align`, `anchor`, `textColor`, `backgroundColor`, `fontSize`, `fontFamily`) are rejected. On WordPress older than 7.0, `register()` returns `false` and the rest of the library keeps working.
