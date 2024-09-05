<?php
/**
 * Integration for h5p.
 *
 * @package openlab-modules
 */

namespace OpenLab\Modules\Integrations;

use OpenLab\Modules\Editor;
use OpenLab\Modules\Module;
use OpenLab\Modules\Schema;

/**
 * Integration for h5p.
 */
class H5P {
	/**
	 * Private constructor.
	 *
	 * @return void
	 */
	private function __construct() {}

	/**
	 * Gets the singleton instance.
	 *
	 * @return \OpenLab\Modules\Integrations\WWPE
	 */
	public static function get_instance() {
		static $instance;

		if ( empty( $instance ) ) {
			$instance = new self();
		}

		return $instance;
	}

	/**
	 * Initializes the integration.
	 *
	 * @return void
	 */
	public function init() {
		add_action( 'wp_enqueue_scripts', [ $this, 'enqueue_assets' ], 100 );
		add_filter( 'openlab_modules_module_requirements', [ $this, 'add_h5p_requirement' ], 10, 2 );
	}

	/**
	 * Sets up the post for use with h5p.
	 *
	 * @return void
	 */
	public function enqueue_assets() {
		// Only set up data for modules or posts belonging to modules.
		if ( ! $this->is_module_page() ) {
			return;
		}

		$blocks_asset_file = Editor::get_blocks_asset_file( 'h5p' );

		wp_enqueue_script(
			'openlab-modules-h5p',
			OPENLAB_MODULES_PLUGIN_URL . '/build/h5p.js',
			[],
			$blocks_asset_file['version'],
			true
		);
	}

	/**
	 * Detects and adds the H5P requirement for a module.
	 *
	 * Looks for the presence of the [h5p] shortcode in the module content.
	 *
	 * @param string[] $plugin_requirements Array of plugin requirements.
	 * @param int      $module_id           Module ID.
	 * @return string[]
	 */
	public function add_h5p_requirement( $plugin_requirements, $module_id ) { // phpcs:ignore
		$contains_h5p = false;

		$module = Module::get_instance( $module_id );
		if ( ! $module ) {
			return $plugin_requirements;
		}

		$contains_h5p = $this->post_contains_h5p( $module_id );

		if ( ! $contains_h5p ) {
			$module_page_ids = $module->get_page_ids( 'all' );
			foreach ( $module_page_ids as $module_page_id ) {
				if ( $this->post_contains_h5p( $module_page_id ) ) {
					$contains_h5p = true;
					break;
				}
			}
		}

		if ( ! $contains_h5p ) {
			return $plugin_requirements;
		}

		$plugin_requirements['h5p/h5p.php'] = __( 'H5P', 'openlab-modules' );

		return $plugin_requirements;
	}

	/**
	 * Determine whether a post contains an H5P shortcode.
	 *
	 * @param int $post_id Post ID.
	 * @return bool
	 */
	private function post_contains_h5p( $post_id ) {
		$post = get_post( $post_id );
		if ( ! $post ) {
			return false;
		}

		return has_shortcode( $post->post_content, 'h5p' );
	}

	/**
	 * Determine whether the current page is a module, or a "section" page belonging to a module.
	 *
	 * @return bool
	 */
	private function is_module_page() {
		$is_module_page = false;
		$post           = get_post();
		if ( is_singular( Schema::get_module_post_type() ) ) {
			$is_module_page = true;
		} elseif ( is_singular() ) {
			$module_ids     = $post ? Module::get_module_ids_of_page( $post->ID ) : [];
			$is_module_page = ! empty( $module_ids );
		}

		return $is_module_page;
	}
}
