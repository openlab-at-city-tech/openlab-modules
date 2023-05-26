<?php
/**
 * Handles API registration.
 *
 * @package openlab-modules
 */

namespace OpenLab\Modules;

/**
 * Handles API registration.
 */
class API {
	/**
	 * Endpoints.
	 *
	 * @var \WP_REST_Controller[]
	 */
	public $endpoints = [];

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
	 * Initializes API integration.
	 *
	 * @return void
	 */
	public function init() {
		add_action( 'rest_api_init', [ $this, 'register_endpoints' ] );
	}

	/**
	 * Registers endpoints.
	 *
	 * @return void
	 */
	public function register_endpoints() {
		$class_names = [ 'ModulePageIds' ];

		foreach ( $class_names as $class_name ) {
			$class_name_with_namespace      = __NAMESPACE__ . '\\Endpoints\\' . $class_name;
			$this->endpoints[ $class_name ] = new $class_name_with_namespace();
			$this->endpoints[ $class_name ]->register_routes();
		}
	}
}
