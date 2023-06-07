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
		add_action( 'init', [ $this, 'register_metas' ], 16 );

		add_action( 'rest_api_init', [ $this, 'register_rest_fields' ] );
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
					'singular_name'         => __( 'Module', 'openlab-modules' ),
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
				'supports'          => [ 'title', 'editor', 'custom-fields' ],
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
				'hierarchical'      => false,
				'public'            => false,
				'show_in_nav_menus' => true,
				'show_ui'           => true,
				'show_admin_column' => false,
				'query_var'         => true,
				'rewrite'           => false,
				'capabilities'      => [
					'manage_terms' => 'do_not_allow',
					'edit_terms'   => 'do_not_allow',
					'delete_terms' => 'do_not_allow',
					'assign_terms' => 'do_not_allow',
				],
				'labels'            => [
					'name'                       => __( 'Openlab module taxes', 'openlab-modules' ),
					'singular_name'              => _x( 'Openlab module tax', 'taxonomy general name', 'openlab-modules' ),
					'search_items'               => __( 'Search Openlab module taxes', 'openlab-modules' ),
					'popular_items'              => __( 'Popular Openlab module taxes', 'openlab-modules' ),
					'all_items'                  => __( 'All Openlab module taxes', 'openlab-modules' ),
					'parent_item'                => __( 'Parent Openlab module tax', 'openlab-modules' ),
					'parent_item_colon'          => __( 'Parent Openlab module tax:', 'openlab-modules' ),
					'edit_item'                  => __( 'Edit Openlab module tax', 'openlab-modules' ),
					'update_item'                => __( 'Update Openlab module tax', 'openlab-modules' ),
					'view_item'                  => __( 'View Openlab module tax', 'openlab-modules' ),
					'add_new_item'               => __( 'Add New Openlab module tax', 'openlab-modules' ),
					'new_item_name'              => __( 'New Openlab module tax', 'openlab-modules' ),
					'separate_items_with_commas' => __( 'Separate openlab module taxes with commas', 'openlab-modules' ),
					'add_or_remove_items'        => __( 'Add or remove openlab module taxes', 'openlab-modules' ),
					'choose_from_most_used'      => __( 'Choose from the most used openlab module taxes', 'openlab-modules' ),
					'not_found'                  => __( 'No openlab module taxes found.', 'openlab-modules' ),
					'no_terms'                   => __( 'No openlab module taxes', 'openlab-modules' ),
					'menu_name'                  => __( 'Openlab module taxes', 'openlab-modules' ),
					'items_list_navigation'      => __( 'Openlab module taxes list navigation', 'openlab-modules' ),
					'items_list'                 => __( 'Openlab module taxes list', 'openlab-modules' ),
					'most_used'                  => _x( 'Most Used', 'openlab_module_tax', 'openlab-modules' ),
					'back_to_items'              => __( '&larr; Back to Openlab module taxes', 'openlab-modules' ),
				],
				'show_in_rest'      => true,
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

	/**
	 * Registers metas for post types.
	 *
	 * @return void
	 */
	public function register_metas() {
		register_meta(
			'post',
			'module_description',
			[
				'object_subtype' => self::get_module_post_type(),
				'type'           => 'string',
				'single'         => true,
				'show_in_rest'   => true,
				'description'    => __( 'Description', 'openlab-modules' ),
			]
		);

		register_meta(
			'post',
			'module_acknowledgements',
			[
				'object_subtype' => self::get_module_post_type(),
				'type'           => 'string',
				'single'         => true,
				'show_in_rest'   => true,
				'description'    => __( 'Acknowledgements', 'openlab-modules' ),
			]
		);

		register_meta(
			'post',
			'module_page_ids',
			[
				'object_subtype' => self::get_module_post_type(),
				'type'           => 'string',
				'single'         => true,
				'show_in_rest'   => true,
				'description'    => __( 'Module Page IDs', 'openlab-modules' ),
			]
		);

		register_meta(
			'post',
			'link_to_module',
			[
				// @todo This should be dynamic in PHP and also in JS.
				'object_subtype' => 'page',
				'type'           => 'integer',
				'single'         => true,
				'show_in_rest'   => true,
				'description'    => __( 'Link to Module', 'openlab-modules' ),
			]
		);
	}

	/**
	 * Registers fields for REST API responses.
	 *
	 * @return void
	 */
	public function register_rest_fields() {
		register_rest_field(
			'page',
			'editUrl',
			[
				'get_callback'    => function( $object ) {
					if ( ! current_user_can( 'edit_post', $object['id'] ) ) {
						return null;
					}

					return get_edit_post_link( $object['id'], '' );
				},
				'update_callback' => null,
				'schema'          => null,
			]
		);
	}
}
