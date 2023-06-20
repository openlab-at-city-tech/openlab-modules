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

		add_filter( 'rest_page_query', [ $this, 'filter_page_query' ], 10, 2 );
	}

	/**
	 * Registers endpoints.
	 *
	 * @return void
	 */
	public function register_endpoints() {
		$class_names = [ 'ModulePages', 'PageModules' ];

		foreach ( $class_names as $class_name ) {
			$class_name_with_namespace      = __NAMESPACE__ . '\\Endpoints\\' . $class_name;
			$this->endpoints[ $class_name ] = new $class_name_with_namespace();
			$this->endpoints[ $class_name ]->register_routes();
		}
	}

	/**
	 * Filters 'page' REST queries for the excludeModulePages parameter.
	 *
	 * @param mixed[]          $args    Query arguments.
	 * @param \WP_REST_Request $request Request object.
	 * @return mixed[]
	 */
	public function filter_page_query( $args, $request ) {
		$exclude_module_pages = $request->get_param( 'excludeModulePages' );

		if ( ! $exclude_module_pages ) {
			return $args;
		}

		$tax_query = isset( $args['tax_query'] ) && is_array( $args['tax_query'] ) ? $args['tax_query'] : [];

		$tax_query[] = [
			'taxonomy' => Schema::get_module_taxonomy(),
			'operator' => 'NOT EXISTS',
		];

		// phpcs:ignore WordPress.DB.SlowDBQuery
		$args['tax_query'] = $tax_query;

		return $args;
	}
}
