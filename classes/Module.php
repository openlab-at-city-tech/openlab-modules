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
		$post = get_post( $module_id );

		if ( ! $post || Schema::get_module_post_type() !== $post->post_type ) {
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
	 * @return int[]
	 */
	public function get_page_ids() {
		// Stored as JSON for better manipulation in Block Editor.
		$module_page_ids_raw = get_post_meta( $this->id, 'module_page_ids', true );
		if ( ! is_string( $module_page_ids_raw ) ) {
			$module_page_ids_raw = '[]';
		}

		$page_ids = json_decode( $module_page_ids_raw );
		if ( ! is_array( $page_ids ) ) {
			$page_ids = [];
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
	 * Gets IDs of modules to which a page is linked.
	 *
	 * @param int $page_id ID of the page.
	 * @return int[]
	 */
	public static function get_module_ids_of_page( $page_id ) {
		$terms = wp_get_object_terms( $page_id, Schema::get_module_taxonomy() );
		if ( is_wp_error( $terms ) ) {
			return [];
		}
		return wp_list_pluck( $terms, 'term_id' );
	}
}
