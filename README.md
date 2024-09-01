# WordPress - Builder

First of all, if you are looking for a tool that gives you functions in the admin panel, you are in the wrong place!

This builder **was created for developers**. It aims to speed up and facilitate the development of WordPress.

## Installation

### Composer installation

Open `Terminal` on (Linux or MAC) or `PowerShell` (on Windows) insite the theme our plugin path and run:

```
composer require juniorenato/wp-builder
```

At the beginning of the `functions.php` file, if are you using in a theme, or the principal plugin file, insert the following line to import composer dependencies:

```
require_once __DIR__ .'/vendor/autoload.php';
```

## Code examples

Let's create a basic Custom Post Type and Taxonomy.

### Custom Post Type

Registering Custom Post Types with basic configurations:

```
use WPB\PostType\PostType;

function build_custom_post_types()
{
    // Initialize the class
    $ptype = new PostType();

    // Set the post type name "cpt_product" with singular and plural labels
    $ptype->register('cpt_product', 'product', 'products');
}
add_action('init', 'build_custom_post_types');
```

For complete guide, access the [Custom Post Type complete documentation](https://bitbucket.org/juniorenato/hswp-theme-builder/src/master/lib/PostType/README.md).

### Taxonomy

Registering Taxonomy with basic configuration:

```
use WPB\Taxonomy\Taxonomy;

function build_taxonomies()
{
    // Initialize the class
    $tax = new Taxonomy();

    // Add post types to use taxonomy
    $tax->addPostTypes('cpt_product');

    // Set the post type name "tax_type" with singular and plural labels
    $tax->register('tax_type', 'type', 'types');
}
add_action('init', 'build_taxonomies');
```

For complete guide, access the [Taxonomy complete documentation](https://bitbucket.org/juniorenato/hswp-theme-builder/src/master/lib/Taxonomy/README.md).

### Global settings

To use global settings you can use `Builder` class and use for all Post Types or Taxonomies:

```
use WPB\Builder;
use WPB\PostType\PostType;
use WPB\Taxonomy\Taxonomy;

$builder = new Builder();

// Set language of all labels
$builder->setLang('pt-br', true);

// Use prefix for names (default is 'cpt_' and 'tax_')
$builder->setPrefix(true);

// Use in Post Type
$ptype = new PostType($builder);

// Use in Taxonomy
$tax = new Taxonomy($builder);
```

## Versioning

The current version is: **v0.1.3**

### Version LOG
- **v0.1.2** 01/09/2024 - Translation
- **v0.1.2** 23/07/2024 - Add a custom text field to taxonomy
- **v0.1.1** 23/07/2024 - Separate global settings
- **v0.1.0** 13/07/2024 - Post Types and Taxonomies
