<?php
/**
 * Module functionality.
 *
 * @package openlab-modules
 */

namespace OpenLab\Modules;

/**
 * Module object.
 */
class Module {
	/**
	 * Module ID.
	 *
	 * @var int
	 */
	protected $id = 0;

	/**
	 * Constructor.
	 *
	 * @return void
	 */
	public function __construct() {}

	/**
	 * Gets an instance corresponding to a module ID.
	 *
	 * @param int $module_id ID of the module.
	 * @return false|\OpenLab\Modules\Module False if no instance is found.
	 */
	public static function get_instance( $module_id ) {
		$post = self::get_post_by_id( $module_id );
		if ( ! $post ) {
			return false;
		}

		$module     = new self();
		$module->id = $module_id;

		return $module;
	}

	/**
	 * Gets the term ID corresponding to this module.
	 *
	 * @return int
	 */
	public function get_term_id() {
		return \HardG\CptTax\Registry::get_term_id_for_post_id( 'module', $this->id );
	}

	/**
	 * Gets a list of page IDs.
	 *
	 * @param string $type 'publish' to get only those items that are published. 'all' to get all'.
	 *
	 * @return int[]
	 */
	public function get_page_ids( $type = 'all' ) {
		// Stored as JSON for better manipulation in Block Editor.
		$module_page_ids_raw = get_post_meta( $this->id, 'module_page_ids', true );
		if ( ! is_string( $module_page_ids_raw ) ) {
			$module_page_ids_raw = '[]';
		}

		$page_ids = json_decode( $module_page_ids_raw );
		if ( ! is_array( $page_ids ) ) {
			$page_ids = [];
		}

		$page_ids = array_map( 'intval', $page_ids );

		if ( 'publish' === $type ) {
			$page_ids = array_filter(
				$page_ids,
				function ( $page_id ) {
					return 'publish' === get_post_status( $page_id );
				}
			);
		}

		return array_map( 'intval', $page_ids );
	}

	/**
	 * Links a page to the module.
	 *
	 * It will be placed at the end of the list.
	 *
	 * @param int $page_id ID of the page.
	 * @return bool
	 */
	public function link_page_to_module( $page_id ) {
		wp_set_object_terms( $page_id, [ $this->get_term_id() ], Schema::get_module_taxonomy(), true );

		$page_ids   = $this->get_page_ids();
		$page_ids[] = $page_id;

		$updated = update_post_meta( $this->id, 'module_page_ids', wp_json_encode( $page_ids ) );

		return (bool) $updated;
	}

	/**
	 * Unlinks a page from the module.
	 *
	 * @param int $page_id ID of the page.
	 * @return bool
	 */
	public function unlink_page_from_module( $page_id ) {
		wp_remove_object_terms( $page_id, [ $this->get_term_id() ], Schema::get_module_taxonomy() );

		$page_ids = array_filter(
			$this->get_page_ids(),
			function ( $linked_page_id ) use ( $page_id ) {
				return $linked_page_id !== $page_id;
			}
		);

		$updated = update_post_meta( $this->id, 'module_page_ids', wp_json_encode( $page_ids ) );

		return (bool) $updated;
	}

	/**
	 * Gets IDs of modules to which a page is linked.
	 *
	 * @param int $page_id ID of the page.
	 * @return int[] IDs of module CPT objects.
	 */
	public static function get_module_ids_of_page( $page_id ) {
		$terms = wp_get_object_terms( $page_id, Schema::get_module_taxonomy() );
		if ( is_wp_error( $terms ) ) {
			return [];
		}

		$module_ids = array_map(
			function ( $term ) {
				return \HardG\CptTax\Registry::get_post_id_for_term_id( 'module', $term->term_id );
			},
			$terms
		);

		return array_filter( $module_ids );
	}

	/**
	 * Gets the post associated with a module id.
	 *
	 * @param int $post_id Post ID.
	 * @return \WP_Post|null
	 */
	protected static function get_post_by_id( $post_id ) {
		$post = get_post( $post_id );

		if ( ! $post || Schema::get_module_post_type() !== $post->post_type ) {
			return null;
		}

		return $post;
	}

	/**
	 * Gets the post associated with the current module.
	 *
	 * @return \WP_Post|null
	 */
	protected function get_post() {
		return self::get_post_by_id( $this->id );
	}

	/**
	 * Gets the ID of the current module.
	 *
	 * @return int
	 */
	public function get_id() {
		return (int) $this->id;
	}

	/**
	 * Gets the title of the module.
	 *
	 * @return string
	 */
	public function get_title() {
		$post = $this->get_post();

		if ( ! $post ) {
			return '';
		}

		return $post->post_title;
	}

	/**
	 * Gets the URL of the module.
	 *
	 * @return string
	 */
	public function get_url() {
		$post = $this->get_post();

		if ( ! $post ) {
			return '';
		}

		return get_permalink( $post );
	}

	/**
	 * Gets the "navigation title" of the module.
	 *
	 * @return string
	 */
	public function get_nav_title() {
		$post = $this->get_post();

		if ( ! $post ) {
			return '';
		}

		$nav_title = get_post_meta( $post->ID, 'module_nav_title', true );
		if ( ! is_string( $nav_title ) ) {
			$nav_title = '';
		}

		if ( empty( $nav_title ) ) {
			$nav_title = __( 'Module Home', 'openlab-modules' );
		}

		return $nav_title;
	}

	/**
	 * Gets the description of the module.
	 *
	 * @return string
	 */
	public function get_description() {
		$post = $this->get_post();

		if ( ! $post ) {
			return '';
		}

		$description = get_post_meta( $post->ID, 'module_description', true );

		if ( ! is_string( $description ) ) {
			$description = '';
		}

		return $description;
	}

	/**
	 * Is sharing enable for this module?
	 *
	 * @return bool
	 */
	public function is_sharing_enabled() {
		$saved = get_post_meta( $this->id, 'enable_sharing', true );

		if ( '' === $saved && function_exists( 'openlab_group_can_be_cloned' ) && function_exists( 'openlab_get_group_id_by_blog_id' ) ) {
			$group_id = openlab_get_group_id_by_blog_id( get_current_blog_id() );
			if ( $group_id ) {
				return openlab_group_can_be_cloned( $group_id );
			}
		}

		return (bool) $saved;
	}

	/**
	 * Gets a list of modules.
	 *
	 * @return \OpenLab\Modules\Module[]
	 */
	public static function get() {
		$args = [
			'post_type'      => Schema::get_module_post_type(),
			'posts_per_page' => -1,
			'orderby'        => [ 'title' => 'ASC' ],
			'fields'         => 'ids',
		];

		$post_ids = get_posts( $args );

		$modules = array_map(
			function ( $post_id ) {
				return self::get_instance( $post_id );
			},
			$post_ids
		);

		return array_filter( $modules );
	}
}
