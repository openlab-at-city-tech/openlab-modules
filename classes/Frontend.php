<?php
/**
 * Handles frontend integration.
 *
 * @package openlab-modules
 */

namespace OpenLab\Modules;

/**
 * Handles frontend integration.
 */
class Frontend {
	/**
	 * Private constructor.
	 *
	 * @return void
	 */
	private function __construct() {}

	/**
	 * Gets the singleton instance.
	 *
	 * @return \OpenLab\Modules\Frontend
	 */
	public static function get_instance() {
		static $instance;

		if ( empty( $instance ) ) {
			$instance = new self();
		}

		return $instance;
	}

	/**
	 * Initializes frontend integration.
	 *
	 * @return void
	 */
	public function init() {
		add_action( 'wp_enqueue_scripts', [ $this, 'register_assets' ] );
	}

	/**
	 * Registers assets for use on the frontend.
	 *
	 * @return void
	 */
	public function register_assets() {
		$blocks_asset_file = Editor::get_blocks_asset_file();

		wp_register_style(
			'openlab-modules-frontend',
			OPENLAB_MODULES_PLUGIN_URL . '/build/frontend.css',
			[],
			$blocks_asset_file['version']
		);
	}
}
