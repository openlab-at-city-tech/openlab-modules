<?php
/**
 * Handles editor functionality.
 *
 * @package openlab-modules
 */

namespace OpenLab\Modules;

/**
 * Handles editor functionality.
 */
class Editor {
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
	 * Initializes Editor integration.
	 *
	 * @return void
	 */
	public function init() {
		add_action( 'enqueue_block_editor_assets', [ $this, 'enqueue_block_assets' ] );
	}

	/**
	 * Gets block asset info from the build directory.
	 *
	 * Used when enqueuing assets, to help with cache busting during development.
	 *
	 * @access protected
	 * @return array
	 */
	protected function get_blocks_asset_file() {
		$blocks_dir        = ROOT_DIR . '/build/';
		$blocks_asset_file = include $blocks_dir . 'index.asset.php';

		// Replace "wp-blockEditor" with "wp-block-editor".
		$blocks_asset_file['dependencies'] = array_replace(
			$blocks_asset_file['dependencies'],
			array_fill_keys(
				array_keys( $blocks_asset_file['dependencies'], 'wp-blockEditor', true ),
				'wp-block-editor'
			)
		);

		return $blocks_asset_file;
	}

	/**
	 * Enqueue block assets on the Dashboard.
	 *
	 * @since 1.0.0
	 */
	public function enqueue_block_assets() {
		$blocks_asset_file = $this->get_blocks_asset_file();

		wp_enqueue_script(
			'striped-registry-dashboard',
			OPENLAB_MODULES_PLUGIN_URL . '/build/index.js',
			$blocks_asset_file['dependencies'],
			$blocks_asset_file['version'],
			true
		);
	}
}
