# WordPress - Builder

First of all, if you are looking for a tool that gives you functions in the admin panel, you are in the wrong place!

This builder **was created for developers**. It aims to speed up and facilitate the development of WordPress.

1. [Installation](#installation)
    1.1. [Compsoer installation](#composer-installation)

2. [Fast mode](#fast-mode)
    2.1. [Custom Post Types](#custom-post-types)
    2.2. [Taxonomies](#taxonomies)
    2.2. [Pages](#pages)

3. [Post Types](#post-types)
    3.1. [Creating a new custom post type](#post-types)
    3.2. [Aditional helper methods](#aditional-helper-methods)
    3.3. [Change default labels](#change-default-labels)
    3.4. [Change default args](#change-default-args)

4. [Version LOG](#version-log)

---

## Installation

### Composer installation

Open `Terminal` on (Linux or MAC) or `PowerShell` (on Windows) insite the theme our plugin path and run:

```bash
composer require juniorenato/wp-builder
```

At the beginning of the `functions.php` file, if are you using in a theme, or the principal plugin file, insert the following line to import composer dependencies:

```php
// Set composer autoloader
require_once __DIR__ .'/vendor/autoload.php';

// Initialize the builder
new Builder;
```

---

## Fast mode

Let's create a basic Custom Post Type, Taxonomy and admin page.

### Custom Post Types

For complete guide, access the [Post Types documentation](https://github.com/juniorenato/wp-builder/blob/master/lib/PostTypes/README.md).

```php
use WPB\PostTypes\CustomPostType;

// Create a custom post type
(new CustomPostType)->register('company', 'company', 'companies');
```

### Taxonomies

For complete guide, access the [Taxonomiesw complete documentation](https://github.com/juniorenato/wp-builder/blob/master/lib/Taxonomies/README.md).

```php
use WPB\Taxonomies\Taxonomy;

// Create a taxonomy
(new Taxonomy)->register('country', 'company', 'country', 'countries');
```

### Pages

For complete guide, access the [Pages documentation](https://github.com/juniorenato/wp-builder/tree/master/lib/Pages/README.md).

```php
use WPB\Pages\AdminPage;

// Create a Admin Page
(new AdminPage)->register('theme options', function() {
    echo '<h1>Hello World!</h1>';
});
```

---

## Post Types

First of all, you need to know the [register_post_type](https://developer.wordpress.org/reference/functions/register_post_type/) function in the native WordPress library.

Knowing this, let's get started.

### Creating a new custom post type

For a basic configuration, you only need three fields `post_type_name`, `singular_name`, `plural_name`.

Let's start with a post type called **Product** and the `post_type_name` called `cpt_product`:

```php
use WPB\PostType\PostType;

$postType = new CustomPostType

// Set post type key
$postType->setPostType('cpt_product');

// Set post type labels (sungular, plural)
$postType->setLabels('product', 'products');

// Register the new custom post type
$postType->register();
```

### Aditional helper methods

The `CustomPostType` class has helper functions:

```php
// Change menu icon
$postType->icon('dashicons-admin-links');

// Set the menu position
$postType->position(20);
```

### Change default labels

The CPTs have many labels for different areas of the dashboard and website, to modify the default settings you can use the `labels` method.

In WordPress documentation you can know [all labels possibility](https://developer.wordpress.org/reference/functions/get_post_type_labels/).

```php
// You can use a associative array
$postType->labels([
    'menu_name' => 'Products',
    'add_new' => 'Add new product',
]);

// Or for a unique change use two parameters (key and value):
$postType->labels('menu_name', 'Products');
$postType->labels('add_new', 'Add new product');
```

### Change default args

As in `labels` adding arguments can be done with the `args` method, one by one or several at once:

```php
// You can use a array with kay -> value
$args = [
    'show_in_rest' => true,
    'rest_base' => 'product',
];
$postType->addArgs($args);

// Or for a unique change use two parameters (key and value):
$postType->addArgs('show_in_rest', true);
$postType->addArgs('rest_base', 'product');
```

---

## Version LOG

The current version is: **v0.3.0**

### v0.3
- **03/09/2024 | v0.3.0** - Documentation structure

### v0.2
- **03/09/2024 | v0.2.2** - Taxonomy and Admin Page fast mode
- **03/09/2024 | v0.2.1** - Change CPT registration method
- **02/09/2024 | v0.2.0**
    - New post type structure
    - New Boxes
    - Taxonomyes fixes
    - Admin pages and Submenu pages

### v0.1
- **01/09/2024 | v0.1.3** - Translation
- **23/07/2024 | v0.1.2** - Add a custom text field to taxonomy
- **23/07/2024 | v0.1.1** - Separate global settings
- **13/07/2024 | v0.1.0** - Post Types and Taxonomies
