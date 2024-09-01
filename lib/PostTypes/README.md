# Custom Post Types

First of all, you need to know the [register_post_type](https://developer.wordpress.org/reference/functions/register_post_type/) function in the native WordPress library.

Knowing this, let's get started.

## Initialize

Post type registrations should not be hooked before the ‘init’ action.

```
use WPB\PostType\PostType;

function register_cpt()
{
    $ptype = new PostType();

    ...
}
add_action('init', 'register_cpt');
```

## Register with initial parameters

For a basic configuration, you only need three fields `post_type_name`, `singular_name`, `plural_name`.

Let's start with a post type called **Product** and the `post_type_name` called `cpt_product`:


```
$ptype->register('cpt_product', 'product', 'products');
```

Or you can do it like this:

```
$ptype->setName('cpt_product');
$ptype->setLabels('product', 'products');
```

## Change labels

The CPTs have many labels for different areas of the dashboard and website, to modify the default settings you can use the `addLabels` method.

```
$labels = [
    'menu_name' => 'Products',
    'add_new' => 'Add new product',
];

$ptype->addLabels($labels);

// Or for a unique change use two parameters (key and value):
$ptype->addLabels('menu_name', 'Products');
```

In WordPress documentation you can know [all labels possibility](https://developer.wordpress.org/reference/functions/get_post_type_labels/).

## Change CPT args

As in `addLabels` adding arguments can be done with the `addArgs` method, one by one or several at once:

```
$args = [
    'show_in_rest' => true,
    'rest_base' => 'product',
];

$ptype->addArgs($args);

// Or for a unique change use two parameters (key and value):
$ptype->addArgs('show_in_rest', true);
```
