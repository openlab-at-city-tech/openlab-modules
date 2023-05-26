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
}
