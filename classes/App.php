<?php
/**
 * Primary application loader.
 *
 * @package openlab-modules
 */

namespace OpenLab\Modules;

/**
 * Primary application loader.
 */
class App {
	/**
	 * Initializes the application.
	 *
	 * @return void
	 */
	public static function init() {
		$schema = Schema::get_instance();
		$schema->init();

		$editor = Editor::get_instance();
		$editor->init();

		$api = API::get_instance();
		$api->init();

		$admin = Admin::get_instance();
		$admin->init();

		$frontend = Frontend::get_instance();
		$frontend->init();

		if ( function_exists( 'wwpe_block_init' ) ) {
			$wwpe_integration = Integrations\WWPE::get_instance();
			$wwpe_integration->init();
		}

		// We don't do a plugin check because H5P content may be embedded from elsewhere.
		$h5p_integration = Integrations\H5P::get_instance();
		$h5p_integration->init();
	}
}
