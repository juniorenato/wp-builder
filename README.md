# WordPress Builder

WordPress Builder is a PHP library **created for developers**. It is not an admin plugin and does not add a graphical interface to WordPress.

Use it in a theme or plugin to register custom post types, taxonomies, meta boxes, admin pages, form fields, and PHP-only Gutenberg blocks with a small fluent API instead of repeating WordPress boilerplate.

## Contents

- [Requirements](#requirements)
- [Installation](#installation)
- [Directory tree](#directory-tree)
- [Bootstrap](#bootstrap)
- [Custom post types](#custom-post-types)
- [Taxonomies](#taxonomies)
- [Meta boxes](#meta-boxes)
- [Admin pages](#admin-pages)
- [Form fields](#form-fields)
- [Gutenberg blocks (PHP only)](#gutenberg-blocks-php-only)
- [Comments](#comments)
- [License](#license)

## Requirements

- PHP 8.4 or later
- WordPress (PHP-only Gutenberg blocks require WordPress 7.0 or later)

## Installation

### Composer

From your theme or plugin directory:

```bash
composer require juniorenato/wp-builder
```

Load Composer's autoloader before you use the library:

```php
require_once get_stylesheet_directory() . '/vendor/autoload.php';
```

In a plugin, point that path at the plugin’s `vendor/autoload.php`.

### Manual

Copy this package into your project and map the `WPB\` namespace to `lib/` (PSR-4), or require `vendor/autoload.php` if you still run Composer locally.

## Directory tree

```text
wp-builder/
├── composer.json
├── composer.lock
├── LICENSE
├── README.md
├── lang/
│   ├── wpb-en_US.mo
│   ├── wpb-en_US.po
│   ├── wpb-pt_BR.mo
│   └── wpb-pt_BR.po
├── lib/
│   ├── Builder.php
│   ├── Blocks/
│   │   └── Block.php
│   ├── Comments/
│   │   └── Comment.php
│   ├── Forms/
│   │   ├── AdminForm.php
│   │   ├── PostTypeMetaBox.php
│   │   └── TaxonomyCustomField.php
│   ├── MetaBoxes/
│   │   └── MetaBox.php
│   ├── Pages/
│   │   └── AdminPage.php
│   ├── PostTypes/
│   │   ├── CustomPostType.php
│   │   ├── EditPostType.php
│   │   ├── PostType.php
│   │   └── RemovePostType.php
│   └── Taxonomies/
│       └── Taxonomy.php
└── views/
    ├── page.php
    └── form/
        ├── get-field.php
        └── partials/
            ├── checkbox.php
            ├── description.php
            ├── hidden.php
            ├── label.php
            ├── number.php
            ├── radio.php
            ├── rich-text.php
            ├── select.php
            ├── text.php
            └── textarea.php
```

`lib/` is the public PHP API (`WPB\`). `views/` holds admin form and settings page templates. `lang/` ships English and Brazilian Portuguese translations.

## Bootstrap

Instantiate `WPB\Builder` once from your theme or plugin so translations and library services load on `init`.

Theme (`functions.php`):

```php
use WPB\Builder;

require_once get_stylesheet_directory() . '/vendor/autoload.php';

new Builder(Builder::THEME);
```

Plugin:

```php
use WPB\Builder;

require_once __DIR__ . '/vendor/autoload.php';

new Builder(Builder::PLUGIN);
```

Constructors in this library are safe to call from a bootstrap file. Registration that needs translations or WordPress APIs is deferred until `init`.

## Custom post types

### Quick registration

Pass the post type key, singular label, and plural label to the constructor. WordPress receives `register_post_type()` on `init`.

```php
use WPB\PostTypes\CustomPostType;

new CustomPostType('book', 'book', 'books');
```

The fourth argument controls masculine or feminine generated labels (`true` by default):

```php
new CustomPostType('review', 'review', 'reviews', false);
```

### Fluent registration

```php
use WPB\PostTypes\CustomPostType;

$books = new CustomPostType();
$books->setPostType('book')
    ->setLabels('book', 'books')
    ->icon('dashicons-book')
    ->position('20')
    ->supports('thumbnail', true)
    ->supports('excerpt', true)
    ->taxonomies(['genre'])
    ->rewrite('slug', 'books')
    ->args([
        'public'       => true,
        'has_archive'  => true,
        'show_in_rest' => true,
    ]);

$books->register();
```

Default `supports` values are `title` and `editor`. Use `args()` for any argument accepted by [`register_post_type()`](https://developer.wordpress.org/reference/functions/register_post_type/).

### Fields on the post type

Fields added on the post type are stored as post meta and rendered in a meta box:

```php
use WPB\PostTypes\CustomPostType;

$books = new CustomPostType();
$books->setPostType('book');
$books->setLabels('book', 'books');
$books->addTextField('isbn', 'ISBN', 'International Standard Book Number');
$books->addNumberField('pages', 'Pages');
$books->addSelectField('format', 'Format', [
    'hardcover' => 'Hardcover',
    'paperback' => 'Paperback',
    'ebook'     => 'E-book',
]);
$books->register();
```

### Edit an existing post type

Merge arguments into `post`, `page`, or any registered type:

```php
use WPB\PostTypes\EditPostType;

new EditPostType('post', [
    'has_archive' => true,
    'menu_icon'   => 'dashicons-welcome-write-blog',
]);
```

Or configure first, then apply:

```php
use WPB\PostTypes\EditPostType;

$posts = new EditPostType();
$posts->setPostType('page');
$posts->args('show_in_rest', true);
$posts->edit();
```

### Hide a post type

This does not unregister the type. It hides it from menus, search, and public queries:

```php
use WPB\PostTypes\RemovePostType;

new RemovePostType('attachment');
```

## Taxonomies

### Quick registration

```php
use WPB\Taxonomies\Taxonomy;

new Taxonomy('genre', 'book', 'genre', 'genres');
```

The second argument can be one post type or a list of slugs.

### Fluent registration

```php
use WPB\Taxonomies\Taxonomy;

$genre = new Taxonomy();
$genre->taxonomy('genre');
$genre->postTypes(['book']);
$genre->setLabels('genre', 'genres');
$genre->rewrite('slug', 'genres');
$genre->args([
    'hierarchical'      => true,
    'show_admin_column' => true,
    'show_in_rest'      => true,
]);
$genre->register();
```

### Term fields

Custom fields appear on the add-term and edit-term screens and are saved as term meta:

```php
use WPB\Taxonomies\Taxonomy;

$genre = new Taxonomy();
$genre->taxonomy('genre');
$genre->postTypes('book');
$genre->setLabels('genre', 'genres');
$genre->addTextField('accent_color', 'Accent color');
$genre->addTextareaField('intro', 'Archive intro');
$genre->register();
```

## Meta boxes

Use a standalone meta box when you want an explicit title, screen, context, or priority. Attach it to a post type with `metaBox()`:

```php
use WPB\MetaBoxes\MetaBox;
use WPB\PostTypes\CustomPostType;

$details = new MetaBox();
$details->title('Book details')
    ->context('side')
    ->priority('high');
$details->addTextField('isbn', 'ISBN');
$details->addNumberField('pages', 'Pages');

$books = new CustomPostType();
$books->setPostType('book');
$books->setLabels('book', 'books');
$books->metaBox($details);
$books->register();
```

You can also register a meta box on its own against any screen:

```php
use WPB\MetaBoxes\MetaBox;

$seo = new MetaBox();
$seo->metaBoxId('page_seo');
$seo->title('SEO')
    ->screen('page')
    ->context('normal');
$seo->addTextField('meta_title', 'Meta title');
$seo->addTextareaField('meta_description', 'Meta description');
$seo->register();
```

## Admin pages

### Settings page with fields

If you do not pass a render callback, the library renders a Settings API page (`options.php`) and registers each field with `register_setting()`.

Top-level menu:

```php
use WPB\Pages\AdminPage;

$settings = new AdminPage();
$settings->title('Theme settings')
    ->icon('dashicons-admin-generic')
    ->position('59');
$settings->addTextField('company_name', 'Company name', 'Shown in the footer.');
$settings->addTextareaField('footer_note', 'Footer note');
$settings->register();
```

Submenu (parent slugs such as `themes` become `themes.php`):

```php
use WPB\Pages\AdminPage;

$footer = new AdminPage();
$footer->title('Footer settings');
$footer->parent('themes');
$footer->addTextField('footer_text', 'Footer text');
$footer->register();
```

### Custom render callback

```php
use WPB\Pages\AdminPage;

new AdminPage('Reports', function (): void {
    echo '<div class="wrap"><h1>Reports</h1></div>';
});
```

Values are stored as options. Read them with `get_option('company_name')`.

## Form fields

`CustomPostType`, `Taxonomy`, `MetaBox`, and `AdminPage` share the same field helpers.

| Method | Field type |
| --- | --- |
| `addTextField($name, $label, $description = '', $class = '', $attrs = [])` | Text |
| `addTextareaField($name, $label, $description = '', $class = '', $attrs = [])` | Textarea |
| `addNumberField($name, $label, $description = '', $class = '', $attrs = [])` | Number |
| `addSelectField($name, $label, $options, $multiple = false, ...)` | Select |
| `addRadioField($name, $label, $options, ...)` | Radio |
| `addCheckboxField($name, $label, $options = [], $multiple = true, ...)` | Checkbox |
| `addRichTextField($name, $label, $editorSettings = [], ...)` | `wp_editor()` |
| `formField($type, $name, $label, $attributes, $options, $description)` | Any allowed type |

Storage depends on the parent object:

- post type / meta box: post meta
- taxonomy: term meta
- admin page: options

Submitted values are sanitized (`sanitize_text_field()`, `sanitize_textarea_field()`, `wp_kses_post()`, and related helpers).

## Gutenberg blocks (PHP only)

Requires **WordPress 7.0+**. Registers a block with PHP only (`supports.autoRegister`): no JavaScript, no `block.json`, and no build step.

On older WordPress versions, `register()` returns `false` and the rest of the library keeps working.

```php
use WPB\Blocks\Block;

$cta = new Block();
$cta->name('my-theme/cta')
    ->title('Call to Action')
    ->description('Banner with title and button')
    ->category('widgets')
    ->icon('megaphone')
    ->keywords(['cta', 'banner'])
    ->addStringAttribute('heading', 'Heading', 'Get started')
    ->addBooleanAttribute('featured', 'Featured', false)
    ->addIntegerAttribute('max_items', 'Max items', 3)
    ->addEnumAttribute('size', 'Size', ['Small', 'Medium', 'Large'], 'Medium')
    ->supports([
        'align'   => ['wide', 'full'],
        'color'   => ['text' => true, 'background' => true],
        'spacing' => ['padding' => true, 'margin' => true],
    ])
    ->stylesheet(get_theme_file_uri('assets/cta.css'), 'my-theme-cta')
    ->render(function (array $attributes): string {
        $wrapper = get_block_wrapper_attributes(['class' => 'my-cta']);

        return sprintf(
            '<div %1$s><h2>%2$s</h2></div>',
            $wrapper,
            esc_html($attributes['heading'] ?? '')
        );
    });

$cta->register();
```

Constructor shortcut when name, title, and render callback are already known:

```php
new Block('my-theme/cta', 'Call to Action', $renderCallback);
```

Use [`get_block_wrapper_attributes()`](https://developer.wordpress.org/reference/functions/get_block_wrapper_attributes/) in the render callback so color, spacing, and border supports print on the wrapper. `stylesheet()` loads CSS on the front end and in the editor.

### Block limits

Inspector controls are generated only for `string`, `integer`, `number`, `boolean`, and `string` plus `enum`. There are no inner blocks, no static `save`, and no media, textarea, or rich-text fields.

Attribute names reserved by block supports are rejected: `style`, `className`, `align`, `anchor`, `textColor`, `backgroundColor`, `fontSize`, `fontFamily`.

## Comments

Disable comments, pings, the comments admin screen, and comment support on post types:

```php
use WPB\Comments\Comment;

(new Comment())->disable();
```

## License

[GPL-3.0-or-later](LICENSE)

Further notes and older examples live in the [project wiki](https://github.com/juniorenato/wp-builder/wiki).
