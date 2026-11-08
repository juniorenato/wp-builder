# WordPress Builder

WordPress Builder is a PHP library **created for developers**. It is not an admin plugin and does not add a graphical interface to WordPress.

Use it in a theme or plugin to register custom post types, taxonomies, meta boxes, admin pages, form fields, and server-rendered Gutenberg blocks with a small fluent API instead of repeating WordPress boilerplate.

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
- [Performance](#performance)
- [Security](#security)
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
├── tests/
│   └── field-value.php
├── assets/
│   ├── css/
│   │   ├── admin-image.css
│   │   ├── admin-repeater.css
│   │   └── block-editor.css
│   └── js/
│       ├── admin-image.js
│       ├── admin-repeater.js
│       └── block-editor.js
├── lang/
│   ├── wpb-en_US.mo
│   ├── wpb-en_US.po
│   ├── wpb-pt_BR.mo
│   └── wpb-pt_BR.po
├── lib/
│   ├── Builder.php
│   ├── Notices.php
│   ├── Blocks/
│   │   ├── Block.php
│   │   ├── FieldValue.php
│   │   └── Repeater.php
│   ├── Comments/
│   │   └── Comment.php
│   ├── Performance/
│   │   ├── CacheScreen.php
│   │   ├── PageCache.php
│   │   └── Performance.php
│   ├── Security/
│   │   └── Security.php
│   ├── Forms/
│   │   ├── AdminForm.php
│   │   ├── PostTypeMetaBox.php
│   │   ├── Repeater.php
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
    ├── performance/
    │   └── cache.php
    ├── blocks/
    │   ├── repeater.php
    │   └── partials/
    │       ├── boolean.php
    │       ├── enum.php
    │       ├── image.php
    │       ├── integer.php
    │       └── string.php
    └── form/
        ├── get-field.php
        └── partials/
            ├── checkbox.php
            ├── control.php
            ├── description.php
            ├── file.php
            ├── hidden.php
            ├── image.php
            ├── label.php
            ├── number.php
            ├── radio.php
            ├── rich-text.php
            ├── repeater-item.php
            ├── repeater.php
            ├── select.php
            ├── text.php
            └── textarea.php
```

`lib/` is the public PHP API (`WPB\`). `views/` holds admin form, block, and settings page templates. `assets/js/block-editor.js` is the shared inspector script for repeaters and post meta fields. `lang/` ships English and Brazilian Portuguese translations.

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

Constructors in this library are safe to call from a bootstrap file. Blocks, post types, taxonomies, and meta boxes are created on `init`. A call made while `init` is running still creates them inside that hook. A call made after `init` has finished waits for the next request.

Invalid arguments do not throw and do not stop PHP. The library ignores that change and shows the message in the WordPress admin, as an error notice, to users who can `manage_options`.

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
| `addImageField($name, $label, $description = '', $class = '', $attrs = [], $multiple = false)` | Media library image |
| `addFileField($name, $label, $description = '', $class = '', $attrs = [], $multiple = false)` | Media library file |
| `addRichTextField($name, $label, $editorSettings = [], ...)` | `wp_editor()` |
| `formField($type, $name, $label, $attributes, $options, $description)` | Any allowed scalar type |
| `repeater($name, $label, $description = '')` | Repeater |

Storage depends on the parent object:

- post type / meta box: post meta
- taxonomy: term meta
- admin page: options

Submitted values are sanitized (`sanitize_text_field()`, `sanitize_textarea_field()`, `wp_kses_post()`, and related helpers). One image or file is stored as the attachment ID, and an empty value is an empty string. `multiple: true` stores a list of attachment IDs. An empty list is saved when every item is removed. An image field discards a value that is not an image. A file field discards a value that is not an attachment. Duplicate IDs are stored once, in the order they were chosen.

`addImageField()` opens the WordPress media modal (`wp.media`), limited to images. `addFileField()` opens the same modal for any file. With `multiple: true`, the modal accepts several items and each later choice is added to the list. Both methods work on a post type, a meta box, a taxonomy, an admin page, and inside a repeater.

```php
->addImageField('photo', 'Photo')
->addImageField('gallery', 'Gallery', multiple: true)
->addFileField('download', 'Download')
->addFileField('documents', 'Documents', multiple: true)
```

### Repeaters

`repeater()` stores a list under one meta key or option. It works on a post type, a meta box, a taxonomy, and an admin page. Subfields use the same scalar methods as the form. `end()` returns to the parent repeater, or to the form at the root.

```php
$details->repeater('authors', 'Authors')
    ->min(1)
    ->max(12)
    ->addTextField('name', 'Name')
    ->addSelectField('role', 'Role', [
        'author' => 'Author',
        'editor' => 'Editor',
    ])
    ->end();
```

```php
get_post_meta($post_id, 'authors', true);
// [
//     ['name' => 'Ada', 'role' => 'author'],
// ]
```

A taxonomy reads that list with `get_term_meta()`. An admin page reads it with `get_option()`.

Nested repeaters use the same call. Inside a repeater, the scalar types are text, textarea, number, select, radio, checkbox, image, and file. Rich text stays on the parent form, because `wp_editor()` cannot be cloned into a new row.

`min()` shows empty rows until the form reaches that count. Saving fewer rows is allowed; the next edit screen fills the gap. `max()` disables Add and discards extra submitted rows. `max(0)` has no ceiling.

The screen shows Add, Remove, Move up, and Move down. Clearing every row still saves an empty list.

Names at one level must be unique. A name may contain only letters, numbers, hyphens, and underscores.

## Gutenberg blocks

Requires **WordPress 7.0+**. Registers a server-rendered block (`supports.autoRegister`): no `block.json` and no build step.

On older WordPress versions, `register()` returns `false` and the rest of the library keeps working.

Field values are stored in post meta. The meta key is the block name with `/` replaced by `_`. Each insertion has its own entry in that value, keyed by `wpbId`. The id is saved in the block comment, so the same block can be placed twice on one page with different data. `get_post_meta()` returns that map. The render callback receives the fields for that insertion only.

Call `saveAsContent()` to store each insertion's array in its own block comment instead, with no post meta.

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

```php
get_post_meta($post_id, 'my-theme_cta', true);
// [
//     '6f1c1b2a-3d4e-4f5a-8b6c-7d8e9f0a1b2c' => [
//         'heading' => 'Get started',
//         'featured' => false,
//         'max_items' => 3,
//         'size' => 'Medium',
//     ],
// ]
```

```html
<!-- wp:my-theme/cta {"wpbId":"6f1c1b2a-3d4e-4f5a-8b6c-7d8e9f0a1b2c"} /-->
```

A post saved before this split stores one flat field array. The next edit in the block editor copies those values onto each insertion already on the page. A later insertion starts empty. Duplicating a block on the same page copies its values into a new id. Pasting it into another post starts from the field defaults, because the values live in the original post's meta.

Constructor shortcut when name, title, and render callback are already known:

```php
new Block('my-theme/cta', 'Call to Action', $renderCallback);
```

Use [`get_block_wrapper_attributes()`](https://developer.wordpress.org/reference/functions/get_block_wrapper_attributes/) in the render callback so color, spacing, and border supports print on the wrapper. `stylesheet()` loads CSS on the front end and in the editor.

### Public HTML

`render()` runs when it was set. The callback receives normalized fields, and an image is the attachment ID. Its return value is the block HTML.

Otherwise the theme template is the block HTML:

- `template('template-parts/blocks/hero')` loads `template-parts/blocks/hero.php` from the child theme, then the parent. A trailing `.php` is removed. `template('')` is ignored, reported in the admin, and the block falls back to the convention below.
- A `template()` slug that does not exist is reported in the admin. The block then prints the mount point.
- With no `template()` call, `my-theme/team` loads `template-parts/blocks/my-theme/team.php` when that file exists. A missing convention file is silent.

`get_template_part()` receives `$args` with `attributes`, `content`, `block`, and `name`. `attributes` matches `data-wpb-props`. Call `get_block_wrapper_attributes()` inside the template when the markup needs alignment, color, or spacing.

The mount point is a single element:

```html
<div class="wpb-block" data-wpb-block="my-theme/team" data-wpb-props="{...}"></div>
```

`get_block_wrapper_attributes()` supplies the wrapper attributes, including `wpb-block`. `data-wpb-props` is the field JSON. Image fields are objects `{id, url, alt, width, height, srcset}` using the `large` size. There is no `data-wpb-media` attribute. On the block renderer REST route the mount also contains `<p class="wpb-block-placeholder">Title</p>`. That paragraph is absent on the public page.

A block with a name and a title registers even when it has no `render()` callback and no repeater.

### Repeaters

A repeater is a list of fields. It can contain another repeater. The editor always shows an Add button.

```php
$team = new Block();
$team->name('my-theme/team')
    ->title('Team')
    ->repeater('sections', 'Sections')
        ->min(1)
        ->addStringAttribute('title', 'Title')
        ->repeater('members', 'Members')
            ->addImageAttribute('photo', 'Photo')
            ->addStringAttribute('name', 'Name')
            ->end()
        ->end()
    ->register();
```

```php
get_post_meta($post_id, 'my-theme_team', true);
// [
//     '6f1c1b2a-3d4e-4f5a-8b6c-7d8e9f0a1b2c' => [
//         'sections' => [
//             [
//                 'title' => 'Leadership',
//                 'members' => [
//                     ['photo' => 123, 'name' => 'Ada'],
//                 ],
//             ],
//         ],
//     ],
// ]
```

`photo` is an attachment ID. `0` means no image. `min()` and `max()` limit the list in the editor. `max(0)` has no ceiling. On the front, a shorter list still renders.

The stored value and the `render()` callback keep that ID. The theme template and `data-wpb-props` replace the field in place with `{id, url, alt, width, height, srcset}` at size `large`. `0`, or an attachment with no URL, becomes `null`.

Item fields: `addStringAttribute`, `addIntegerAttribute`, `addBooleanAttribute`, `addEnumAttribute`, `addImageAttribute`. A true boolean adds `wpb-repeater__item--{name}` to the item. Field names must be unique among siblings.

`saveAsContent()` keeps the same array in the block comment:

```php
$team->saveAsContent();
```

```html
<!-- wp:my-theme/team {"sections":[{"title":"Leadership","members":[{"photo":123,"name":"Ada"}]}]} /-->
```

A block that defines `render()` receives the normalized array and prints its own HTML. An image in that array is still the attachment ID.

Without `render()`, the block loads a theme template when one exists and otherwise prints a React mount point. `views/blocks/repeater.php` is not the public render. `Repeater::renderItems()` still includes that file.

Blocks saved in post meta, and any block with a repeater, load `assets/js/block-editor.js` in the editor. Scalar blocks that call `saveAsContent()` and have no repeater stay PHP-only.

### Core blocks

`Builder` attaches a `render_block` filter. A theme file replaces the public HTML of a core block when the file exists. `core/paragraph` uses `template-parts/blocks/core/paragraph.php`. `core/image`, `core/group`, and `core/navigation` follow the same path: `template-parts/blocks/core/{slug}.php`.

No file means the WordPress HTML is unchanged, and the admin shows no notice. The template output replaces the block. A template that prints nothing leaves the block empty.

`$args` contains:

- `attributes`: the attributes WordPress stored, without rewriting images
- `content`: the HTML WordPress rendered, including inner blocks
- `block`: the `WP_Block` instance
- `name`: the block name, such as `core/paragraph`

Blocks registered through `WPB\Blocks\Block` are not passed through this replacement. Core blocks do not get a React mount point. Their editor remains the Gutenberg editor. The template applies wherever WordPress runs `render_block`, including the server preview of a dynamic block.

### Block limits

Content-mode inspector controls are generated for `string`, `integer`, `number`, `boolean`, and `string` plus `enum`. Meta-mode controls, repeaters, and image fields are drawn by the shared editor script. There are no inner blocks, no static `save`, and no textarea or rich-text fields.

Attribute names reserved by block supports are rejected: `style`, `className`, `align`, `anchor`, `textColor`, `backgroundColor`, `fontSize`, `fontFamily`.

## Comments

Disable comments, pings, the comments admin screen, and comment support on post types:

```php
use WPB\Comments\Comment;

(new Comment())->disable();
```

## Performance

Opt-in front-end cleanup. An empty constructor does nothing. A list entry calls the method with its default. Map a name to an integer when the method takes one. `false` skips that entry.

```php
use WPB\Performance\Performance;

new Performance([
    'disableEmoji',
    'cleanHead',
    'disableEmbeds',
    'heartbeat' => 120,
    'revisions' => 5,
    'emptyTrash' => 7,
    'images',
    'enableCache' => 3600,
]);
```

Calling a method directly is the same thing: `(new Performance())->disableEmoji()`. `enableAll()` turns on every feature with its default. Put a custom number before `enableAll` and that number stays.

```php
new Performance([
    'heartbeat' => 120,
    'enableAll',
]);

(new Performance())->enableAll();
```

- `disableEmoji()` removes the emoji script, inline styles, feed and mail rewriting, the TinyMCE emoji plugin, and emoji CDN hints. Other `s.w.org` hints stay.
- `cleanHead()` removes the RSD, Windows Live Writer, generator, shortlink, and adjacent-post tags from `wp_head`.
- `disableEmbeds()` removes oEmbed discovery links and deregisters `wp-embed`.
- `heartbeat(int $seconds = 60)` sets the Heartbeat interval. WordPress accepts 1 to 3600 seconds. Anything outside that range uses 60.
- `revisions(int $limit = 5)` caps revisions through `wp_revisions_to_keep`. Post types without revision support stay at zero. A `WP_POST_REVISIONS` value of `0` or `false` stays off. Older revisions are trimmed the next time that post is saved. `0` turns revisions off.
- `emptyTrash(int $days = 7)` replaces the daily trash cleanup. `EMPTY_TRASH_DAYS` is already defined by the time the theme loads, so the class cannot change that constant. Auto-draft cleanup is unchanged. `0` deletes trash on the next run.
- `images()` saves new JPEG and PNG intermediate sizes as AVIF when the image editor supports it, otherwise WebP. The original upload is unchanged. Existing `srcset` behavior stays. `loading="lazy"` and `decoding="async"` are added only when WordPress left them off, the image has width and height, and it is not `fetchpriority="high"`.
- `enableCache(int $seconds = 3600)` is the call that starts page cache. Without it, nothing is stored or served. It caches anonymous `GET` HTML after WordPress boots. A fresh file is sent at `template_redirect` and the template does not render again. Query strings, logged-in users, admin, AJAX, cron, REST, previews, feeds, and URLs with a file extension other than `.html` are skipped. A file is stored only when the response status is 200 and the content type is HTML. Each page editor has a sidebar checkbox, **Disable cache**, which skips that page. Each HTML file is named with the MD5 of its URL, for example `0ac7ec625f71e888fe9db6f53d140195.html`. The readable URL stays in the matching `.json` file. Files live in `wp-content/cache/wpb/` until `$seconds` passes.

`enableCache()` adds **Tools → Cache** through `AdminPage`, as a submenu of Tools. While the cache is on, the dashboard lists the cached URLs. The status checkbox and one multiple select per public post type are AdminPage fields stored as options. Each post type uses the option `wpb_page_cache_pages_{post_type}`. The cached URL table is rendered below that form and clears files directly. Saving a public post clears that post URL, the post type archive and its `/page/N/` URLs, and the pages chosen for that post type. A post type without an archive has no archive URL to clear; link the listing page on this screen. The site root only clears `/` and `/page/N/`.

## Security

Opt-in request hardening. Same constructor rules as performance. `DISALLOW_FILE_EDIT` still belongs in `wp-config.php`.

```php
use WPB\Security\Security;

new Security([
    'disableXmlRpc',
    'disableUserEnumeration',
]);

(new Security())->enableAll();
```

- `disableXmlRpc()` returns 403 from `xmlrpc.php`, removes XML-RPC methods, drops the `X-Pingback` header, and removes the RSD link.
- `disableUserEnumeration()` sends anonymous `/?author=1` requests to the home URL before the canonical redirect can reveal the user slug. Author archives that already use the slug stay public. Guests receive 401 for `/wp/v2/users`. Logged-in users keep that route for the editor. `/wp/v2/users/me` is left to WordPress.

## License

[GPL-3.0-or-later](LICENSE)

Further notes and older examples live in the [project wiki](https://github.com/juniorenato/wp-builder/wiki).
