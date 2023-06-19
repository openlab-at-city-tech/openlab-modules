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
		add_action( 'init', [ $this, 'maybe_flush_rewrite_rules' ], 1000 );

		add_action( 'rest_api_init', [ $this, 'register_rest_fields' ] );

		// Save actions.
		add_action( 'updated_post_meta', [ $this, 'validate_module_pages' ], 10, 3 );
		add_action( 'save_post_' . self::get_module_post_type(), [ $this, 'maybe_create_all_modules_page' ] );
		add_action( 'before_delete_post', [ $this, 'remove_page_from_modules' ] );

		// edit.php mods.
		add_filter( 'manage_' . self::$module_post_type . '_posts_columns', [ $this, 'module_custom_columns' ] );
		add_action( 'manage_' . self::$module_post_type . '_posts_custom_column', [ $this, 'module_custom_column_contents' ], 10, 2 );
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
					'archives'              => __( 'Module Archives', 'openlab-modules' ),
					'attributes'            => __( 'Module Attributes', 'openlab-modules' ),
					'insert_into_item'      => __( 'Insert into Module', 'openlab-modules' ),
					'uploaded_to_this_item' => __( 'Uploaded to this Module', 'openlab-modules' ),
					'featured_image'        => _x( 'Featured Image', 'openlab_module', 'openlab-modules' ),
					'set_featured_image'    => _x( 'Set featured image', 'openlab_module', 'openlab-modules' ),
					'remove_featured_image' => _x( 'Remove featured image', 'openlab_module', 'openlab-modules' ),
					'use_featured_image'    => _x( 'Use as featured image', 'openlab_module', 'openlab-modules' ),
					'filter_items_list'     => __( 'Filter Modules list', 'openlab-modules' ),
					'items_list_navigation' => __( 'Modules list navigation', 'openlab-modules' ),
					'items_list'            => __( 'Modules list', 'openlab-modules' ),
					'new_item'              => __( 'New Module', 'openlab-modules' ),
					'add_new'               => __( 'Add New', 'openlab-modules' ),
					'add_new_item'          => __( 'Add New Module', 'openlab-modules' ),
					'edit_item'             => __( 'Edit Module', 'openlab-modules' ),
					'view_item'             => __( 'View Module', 'openlab-modules' ),
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
				'has_archive'       => false,
				'rewrite'           => [
					'slug' => 'modules',
				],
				'query_var'         => true,
				'menu_position'     => 10,
				'menu_icon'         => 'dashicons-excerpt-view',
				'show_in_rest'      => true,
				'rest_base'         => 'openlab_module',
				'template'          => [
					[ 'openlab-modules/module-navigation' ],
					[
						'core/heading',
						[
							'placeholder' => __( 'Introduction', 'openlab-modules' ),
						],
					],
					[
						'core/paragraph',
						[
							'style'   => [
								'color' => [
									'background' => '#eeeeee',
								],
							],
							'content' => __( '<strong>Faculty:</strong> Please edit this page to organize any additional course resources that you\'d like to share with your students. Please delete this informational block when you are ready to share your site with your students. For help working with OpenLab Course sites, visit <a href="https://openlab.citytech.cuny.edu/blog/help/creating-a-course-faculty-only/">OpenLab Help</a>.', 'openlab-modules' ),
						],
					],
					[
						'core/paragraph',
						[
							'placeholder' => __( 'Add an introduction to this module. Lorem ipsum dolor sit amet, consectetur adipiscing elit, sed do eiusmod tempor incididunt ut labore et dolore magna aliqua. Ut enim ad minim veniam, quis nostrud exercitation ullamco laboris nisi ut aliquip ex ea commodo consequat. Duis aute irure dolor in reprehenderit in voluptate velit esse cillum dolore eu fugiat nulla pariatur. Excepteur sint occaecat cupidatat non proident, sunt in culpa qui officia deserunt mollit anim id est laborum.', 'openlab-modules' ),
						],
					],
				],
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
				'show_in_nav_menus' => false,
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

	/**
	 * Performs validation tasks when module_page_ids array is saved.
	 *
	 * - Ensures that pages in the module_page_ids array are linked via taxonomy to the module.
	 * - Inserts Navigation block, if necessary.
	 *
	 * @param int    $meta_id   Meta ID.
	 * @param int    $module_id Post ID.
	 * @param string $meta_key  Meta key.
	 * @return void
	 */
	public function validate_module_pages( $meta_id, $module_id, $meta_key ) {
		if ( 'module_page_ids' !== $meta_key ) {
			return;
		}

		$module = Module::get_instance( $module_id );
		if ( ! $module ) {
			return;
		}

		// Taxonomy linking.
		$module_page_ids = $module->get_page_ids();
		$module_term_id  = $module->get_term_id();
		foreach ( $module_page_ids as $page_id ) {
			if ( is_object_in_term( $page_id, self::get_module_taxonomy(), $module_term_id ) ) {
				continue;
			}

			wp_set_object_terms( $page_id, [ $module_term_id ], self::get_module_taxonomy(), true );
		}

		// Navigation insertion.
		$nav_block = [
			'blockName' => 'openlab-modules/module-navigation',
			'attrs'     => [
				'moduleId' => $module_id,
			],
		];

		$nav_block_markup = serialize_block( $nav_block );

		foreach ( $module_page_ids as $page_id ) {
			if ( get_post_meta( $page_id, 'openlab_modules_inserted_navigation_' . $module_id ) ) {
				continue;
			}

			$page_content = get_post_field( 'post_content', $page_id );

			wp_update_post(
				[
					'ID'           => $page_id,
					'post_content' => $nav_block_markup . "\n" . $page_content,
				]
			);

			update_post_meta( $page_id, 'openlab_modules_inserted_navigation_' . $module_id, '1' );
		}
	}

	/**
	 * Conditionally creates the 'All Modules' page when a module is published.
	 *
	 * It will be created once, once there's at least one published module.
	 *
	 * @param int $post_id ID of the post being saved.
	 * @return void
	 */
	public function maybe_create_all_modules_page( $post_id ) {
		// We only create the page once. If it's deleted in the future, don't re-create.
		if ( get_option( 'openlab_created_all_modules_page' ) ) {
			return;
		}

		// Only if the module is published.
		$post = get_post( $post_id );
		if ( ! $post || 'publish' !== $post->post_status ) {
			return;
		}

		$block = [
			'blockName'   => 'core/query',
			'attrs'       => [
				'queryId' => 'all-modules-query',
				'query'   => [
					'perPage'  => 10,
					'pages'    => 0,
					'offset'   => 0,
					'postType' => 'openlab_module',
					'order'    => 'asc',
					'orderBy'  => 'title',
					'author'   => '',
					'search'   => '',
					'exclude'  => array(),
					'sticky'   => '',
					'inherit'  => false,
					'parents'  => array(),
				],
			],
			'innerBlocks' => [
				[
					'blockName'   => 'core/post-template',
					'innerBlocks' => [
						[
							'blockName' => 'core/post-title',
							'attrs'     => [
								'isLink' => true,
							],
						],
					],
				],
				[
					'blockName'   => 'core/query-pagination',
					'innerBlocks' => [
						[
							'blockName' => 'core/query-pagination-previous',
						],
						[
							'blockName' => 'core/query-pagination-numbers',
						],
						[
							'blockName' => 'core/query-pagination-next',
						],
					],
				],
				[
					'blockName'   => 'core/query-no-results',
					'innerBlocks' => [
						[
							'blockName' => 'core/paragraph',
							'attrs'     => [
								'placeholder' => __( 'Add text or blocks that will display when your site has no modules', 'openlab-modules' ),
							],
							'innerHTML' => '<p>' . __( 'There are no modules on this site.', 'openlab-modules' ) . '</p>',
						],
					],
				],
			],
		];

		$serialized_block = Editor::serialize_block_recursive( $block );

		wp_insert_post(
			[
				'post_type'    => 'page',
				'post_status'  => 'draft',
				'post_title'   => __( 'All Modules', 'openlab-modules' ),
				'post_content' => $serialized_block,
			]
		);

		update_option( 'openlab_created_all_modules_page', '1' );
	}

	/**
	 * Removes an item from all linked modules on deletion.
	 *
	 * @param int $post_id Post ID.
	 * @return void
	 */
	public function remove_page_from_modules( $post_id ) {
		$module_ids = Module::get_module_ids_of_page( $post_id );
		if ( ! $module_ids ) {
			return;
		}

		foreach ( $module_ids as $module_id ) {
			$module = Module::get_instance( $module_id );
			if ( ! $module ) {
				continue;
			}

			$module->unlink_page_from_module( $post_id );
		}
	}

	/**
	 * Registers rewrite rules, if necessary.
	 *
	 * @return void
	 */
	public function maybe_flush_rewrite_rules() {
		if ( '1' === get_option( 'openlab_modules_rewrite_rules_flushed' ) ) {
			return;
		}

		flush_rewrite_rules( false );
		update_option( 'openlab_modules_rewrite_rules_flushed', '1' );
	}

	/**
	 * Adds custom columns to Modules edit.php screen.
	 *
	 * @param string[] $columns Column keys and header text.
	 * @return string[]
	 */
	public function module_custom_columns( $columns ) {
		$new_columns = [];
		foreach ( $columns as $key => $column_name ) {
			$new_columns[ $key ] = $column_name;

			if ( 'title' === $key ) {
				$new_columns['module_author'] = __( 'Author', 'openlab-modules' );
			}
		}

		return $new_columns;
	}

	/**
	 * Generates content for Modules edit.php custom columns.
	 *
	 * @param string $column_name String defining the column.
	 * @param int    $post_id     ID of the current post in the loop.
	 * @return void
	 */
	public function module_custom_column_contents( $column_name, $post_id ) {
		if ( 'module_author' === $column_name ) {
			$post = get_post( $post_id );
			if ( $post ) {
				if ( ! function_exists( 'bp_core_get_userlink' ) ) {
					// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
					echo bp_core_get_userlink( $post->post_author ); // @phpstan-ignore-line
				} else {
					$author_id = (int) $post->post_author;
					$author    = get_userdata( $author_id );
					if ( $author ) {
						printf(
							'<a href="%s">%s</a>',
							esc_url( get_author_posts_url( $author_id ) ),
							esc_html( $author->display_name )
						);
					}
				}
			}
		}
	}
}
