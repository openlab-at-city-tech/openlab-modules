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

		$frontend = Frontend::get_instance();
		$frontend->init();
	}
}
