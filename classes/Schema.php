<?php
/**
 * Defines WP data schema.
 *
 * @package openlab-modules
 */

namespace OpenLab\Modules;

/**
 * Defines WP data schema.
 */
class Schema {
	/**
	 * Module post type name.
	 *
	 * @var string
	 */
	protected static $module_post_type = 'openlab_module';

	/**
	 * Module taxonomy name.
	 *
	 * @var string
	 */
	protected static $module_taxonomy = 'openlab_module_tax';

	/**
	 * Private constructor.
	 *
	 * @return void
	 */
	private function __construct() {}

	/**
	 * Gets the singleton instance.
	 *
	 * @return \OpenLab\Modules\Schema
	 */
	public static function get_instance() {
		static $instance;

		if ( empty( $instance ) ) {
			$instance = new self();
		}

		return $instance;
	}

	/**
	 * Initializes the application.
	 *
	 * @return void
	 */
	public function init() {
		add_action( 'init', [ $this, 'register_post_types' ] );
		add_action( 'init', [ $this, 'register_taxonomies' ], 12 );
		add_action( 'init', [ $this, 'set_up_cpttax' ], 14 );
	}

	/**
	 * Gets the post type name for a module.
	 *
	 * @return string
	 */
	public static function get_module_post_type() {
		return self::$module_post_type;
	}

	/**
	 * Gets the name of the module taxonomy.
	 *
	 * @return string
	 */
	public static function get_module_taxonomy() {
		return self::$module_taxonomy;
	}

	/**
	 * Registers post types.
	 *
	 * @return void
	 */
	public function register_post_types() {
		register_post_type(
			self::get_module_post_type(),
			[
				'labels'            => [
					'name'                  => __( 'Modules', 'openlab-modules' ),
					'singular_name'         => __( 'Modules', 'openlab-modules' ),
					'all_items'             => __( 'All Modules', 'openlab-modules' ),
					'archives'              => __( 'Modules Archives', 'openlab-modules' ),
					'attributes'            => __( 'Modules Attributes', 'openlab-modules' ),
					'insert_into_item'      => __( 'Insert into Modules', 'openlab-modules' ),
					'uploaded_to_this_item' => __( 'Uploaded to this Modules', 'openlab-modules' ),
					'featured_image'        => _x( 'Featured Image', 'openlab_module', 'openlab-modules' ),
					'set_featured_image'    => _x( 'Set featured image', 'openlab_module', 'openlab-modules' ),
					'remove_featured_image' => _x( 'Remove featured image', 'openlab_module', 'openlab-modules' ),
					'use_featured_image'    => _x( 'Use as featured image', 'openlab_module', 'openlab-modules' ),
					'filter_items_list'     => __( 'Filter Modules list', 'openlab-modules' ),
					'items_list_navigation' => __( 'Modules list navigation', 'openlab-modules' ),
					'items_list'            => __( 'Modules list', 'openlab-modules' ),
					'new_item'              => __( 'New Modules', 'openlab-modules' ),
					'add_new'               => __( 'Add New', 'openlab-modules' ),
					'add_new_item'          => __( 'Add New Modules', 'openlab-modules' ),
					'edit_item'             => __( 'Edit Modules', 'openlab-modules' ),
					'view_item'             => __( 'View Modules', 'openlab-modules' ),
					'view_items'            => __( 'View Modules', 'openlab-modules' ),
					'search_items'          => __( 'Search Modules', 'openlab-modules' ),
					'not_found'             => __( 'No Modules found', 'openlab-modules' ),
					'not_found_in_trash'    => __( 'No Modules found in trash', 'openlab-modules' ),
					'parent_item_colon'     => __( 'Parent Modules:', 'openlab-modules' ),
					'menu_name'             => __( 'Modules', 'openlab-modules' ),
				],
				'public'            => true,
				'hierarchical'      => false,
				'show_ui'           => true,
				'show_in_nav_menus' => true,
				'supports'          => [ 'title', 'editor' ],
				'has_archive'       => true,
				'rewrite'           => [
					'slug' => 'modules',
				],
				'query_var'         => true,
				'menu_position'     => 10,
				'menu_icon'         => 'dashicons-excerpt-view',
				'show_in_rest'      => true,
				'rest_base'         => 'openlab_module',
			]
		);
	}

	/**
	 * Registers taxonomies.
	 *
	 * @return void
	 */
	public function register_taxonomies() {
		register_taxonomy(
			self::get_module_taxonomy(),
			[ 'post', 'page' ],
			[
				'hierarchical'          => false,
				'public'                => false,
				'show_in_nav_menus'     => true,
				'show_ui'               => true,
				'show_admin_column'     => false,
				'query_var'             => true,
				'rewrite'               => false,
				'capabilities'          => [
					'manage_terms' => 'edit_posts',
					'edit_terms'   => 'edit_posts',
					'delete_terms' => 'edit_posts',
					'assign_terms' => 'edit_posts',
				],
				'labels'                => [
					'name'                       => __( 'Openlab module taxes', 'YOUR-TEXTDOMAIN' ),
					'singular_name'              => _x( 'Openlab module tax', 'taxonomy general name', 'YOUR-TEXTDOMAIN' ),
					'search_items'               => __( 'Search Openlab module taxes', 'YOUR-TEXTDOMAIN' ),
					'popular_items'              => __( 'Popular Openlab module taxes', 'YOUR-TEXTDOMAIN' ),
					'all_items'                  => __( 'All Openlab module taxes', 'YOUR-TEXTDOMAIN' ),
					'parent_item'                => __( 'Parent Openlab module tax', 'YOUR-TEXTDOMAIN' ),
					'parent_item_colon'          => __( 'Parent Openlab module tax:', 'YOUR-TEXTDOMAIN' ),
					'edit_item'                  => __( 'Edit Openlab module tax', 'YOUR-TEXTDOMAIN' ),
					'update_item'                => __( 'Update Openlab module tax', 'YOUR-TEXTDOMAIN' ),
					'view_item'                  => __( 'View Openlab module tax', 'YOUR-TEXTDOMAIN' ),
					'add_new_item'               => __( 'Add New Openlab module tax', 'YOUR-TEXTDOMAIN' ),
					'new_item_name'              => __( 'New Openlab module tax', 'YOUR-TEXTDOMAIN' ),
					'separate_items_with_commas' => __( 'Separate openlab module taxes with commas', 'YOUR-TEXTDOMAIN' ),
					'add_or_remove_items'        => __( 'Add or remove openlab module taxes', 'YOUR-TEXTDOMAIN' ),
					'choose_from_most_used'      => __( 'Choose from the most used openlab module taxes', 'YOUR-TEXTDOMAIN' ),
					'not_found'                  => __( 'No openlab module taxes found.', 'YOUR-TEXTDOMAIN' ),
					'no_terms'                   => __( 'No openlab module taxes', 'YOUR-TEXTDOMAIN' ),
					'menu_name'                  => __( 'Openlab module taxes', 'YOUR-TEXTDOMAIN' ),
					'items_list_navigation'      => __( 'Openlab module taxes list navigation', 'YOUR-TEXTDOMAIN' ),
					'items_list'                 => __( 'Openlab module taxes list', 'YOUR-TEXTDOMAIN' ),
					'most_used'                  => _x( 'Most Used', 'openlab_module_tax', 'YOUR-TEXTDOMAIN' ),
					'back_to_items'              => __( '&larr; Back to Openlab module taxes', 'YOUR-TEXTDOMAIN' ),
				],
				'show_in_rest'          => false,
			]
		);
	}

	/**
	 * Sets up CPT-Taxonomy links.
	 *
	 * @return void
	 */
	public function set_up_cpttax() {
		\HardG\CptTax\Registry::register( 'module', self::get_module_post_type(), 'openlab_module_tax' );
	}
}
