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
	 */
	public static function init() {
		$schema = Schema::get_instance();
		$schema->init();
	}
}
