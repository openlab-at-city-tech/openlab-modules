# CPT-TAX

This is a library intended for use in WordPress plugins. The library allows you to ensure that each post of a custom post type is mirrored by a term in a custom taxonomy.

## Use cases: An overview

What is the purpose of this mirroring? WordPress famously does not have native support for post-to-post relationships. Many-to-many relationships only exist between taxonomy terms and posts. This library creates a workaround for this limitation.

As an example, imagine you are building a WordPress site about movies. You have three custom post types: Movies, Awards, and Actors. As part of your system, you'd like the ability to associate Actors with both Movies and Awards. Moreover, these relationships must be many-to-many: a single Actor could linked to many Movies, while a single Movie will likely be linked to many Actors.

In your plugin or theme, you would register a taxonomy corresponding to Actors. Then, tell this library to create a link between the taxonomy (say, `actor_tax`) and the post type (say, `actor_pt`):

```php
\HardG\CptTax\Registry::register( 'actors', 'actor_pt', 'actor_tax' );
```

Each post in the `actor_pt` post type will be mirrored by a term in the `actor_tax` taxonomy. You can then associate an Actor with a Movie using WP's taxonomy tools:

```php
// Fetch the term ID corresponding to the Actor post.
$actor_term_id = \HardG\CptTax\Registry::get_term_id_for_post_id( 'actors', $actor_post_id );

wp_set_object_terms( $movie_post_id, [ $actor_term_id ], 'actor_tax', true );
```

Later, if you want to query for Movies associated with the Actor, you would use a similar technique:

```php
// Fetch the term ID corresponding to the Actor post.
$actor_term_id = \HardG\CptTax\Registry::get_term_id_for_post_id( 'actors', $actor_post_id );

$movies_with_actor = get_posts(
  [
    'post_type' => 'movie_pt',
    'tax_query' => [
      [
        'taxonomy' => 'actor_tax',
	'terms'    => $actor_term_id,
      ]
    ],
  ]
);
```

Similarly, if you want to display a list of Actors associated with a given Movie:

```php
$actor_posts = array_map(
  function( $actor_term ) {
    $actor_post_id = \HardG\CptTax\Registry::get_post_id_for_term_id( 'actors', $actor_term->term_id );
    return get_post( $actor_post_id );
  },
  wp_get_object_terms( $movie_post_id, 'actor_tax' );
);
```

## Methods

### `\HardG\CptTax\Registry::register( $link_key, $post_type, $taxonomy )`

Sets up the link between the post type and the taxonomy. Call this sometime shortly after you've registered the taxonomy and the post type. `$link_key` is a unique identifier for this "pair", which you'll use whenever referencing the pair later.

### `\HardG\CptTax\Registry::get_post_id_for_term_id( $link_key, $term_id )`

Gets the ID of the post associated with a given linked term.

### `\HardG\CptTax\Registry::get_term_id_for_post_id( $link_key, $post_id )`

Gets the ID of the term associated with a given linked post.

## Tips

This library does *not* register the mirrored taxonomy for you. You'll have to do this yourself. In many cases, this taxonomy only meant to provide the links in the database, in which case it's recommended to make the taxonomy non-public:

```php
register_taxonomy(
  'my_taxonomy',
  $my_post_types,
  [
    // ...
    'public' => false,
    'show_ui' => false, // Unless you want to be able to see it for diagnostic purposes in the Dashboard
    'show_in_rest' => false,
    // ...
  ]
);
```

If you leave the UI enabled, you may wish to disable creation of new terms (all term creation is handled by the library), while still allowing terms to be assigned in the UI to posts:

```php
// ...
'capabilities' => [
  'manage_terms' => 'do_not_allow',
  'edit_terms'   => 'do_not_allow',
  'delete_terms' => 'do_not_allow',
],
// ...
```
