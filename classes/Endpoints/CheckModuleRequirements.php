<?php
/**
 * Definition of check-module-requirements endpoint.
 *
 * @package openlab-modules
 */

namespace OpenLab\Modules\Endpoints;

use WP_REST_Controller;
use WP_REST_Request;
use WP_REST_Response;
use WP_REST_Server;
use WP_Error;

use OpenLab\Modules\Module;

/**
 * Definition for check-module-requirements endpoint.
 */
class CheckModuleRequirements extends WP_REST_Controller {
	/**
	 * Registers the routes for the clone-module endpoint.
	 *
	 * @return void
	 */
	public function register_routes() {
		$version   = '1';
		$namespace = 'openlab-modules/v' . $version;

		register_rest_route(
			$namespace,
			'/check-module-requirements/(?P<module_id>\d+)',
			[
				[
					'methods'             => WP_REST_Server::READABLE,
					'callback'            => [ $this, 'get_item' ],
					'permission_callback' => [ $this, 'get_item_permission_callback' ],
					'args'                => [
						'destinationSiteId' => [
							'type'              => 'integer',
							'required'          => true,
							'sanitize_callback' => [ $this, 'sanitize_integer' ],
							'validate_callback' => [ $this, 'validate_integer' ],
						],
					],
				],
			]
		);
	}

	/**
	 * Permisions callback for reading the check-module-requirements endpoint.
	 *
	 * @param WP_REST_Request $request Request object.
	 * @return bool|WP_Error
	 */
	public function get_item_permission_callback( $request ) {
		$destination_site_id = $this->sanitize_integer( $request->get_param( 'destinationSiteId' ) );

		if ( ! $destination_site_id ) {
			return new WP_Error( 'missing_destination_site_id', __( 'Destination site ID is required.', 'openlab-modules' ), [ 'status' => 400 ] );
		}

		$error = null;

		switch_to_blog( $destination_site_id );

		if ( ! current_user_can( 'manage_plugins' ) ) {
			$error = new WP_Error( 'rest_forbidden', __( 'Sorry, you are not allowed to manage plugins on this site.', 'openlab-modules' ), [ 'status' => 403 ] );
		}

		restore_current_blog();

		if ( $error ) {
			return $error;
		}

		return true;
	}

	/**
	 * Callback for reading the check-module-requirements endpoint.
	 *
	 * @param WP_REST_Request $request Request object.
	 * @return WP_REST_Response|WP_Error
	 */
	public function get_item( $request ) {
		$module_id           = $this->sanitize_integer( $request->get_param( 'module_id' ) );
		$destination_site_id = $this->sanitize_integer( $request->get_param( 'destinationSiteId' ) );

		$module = Module::get_instance( $module_id );
		if ( ! $module ) {
			return new WP_Error( 'module_not_found', __( 'Module not found.', 'openlab-modules' ), [ 'status' => 404 ] );
		}

		$module_requirements = $module->get_requirements();

		if ( empty( $module_requirements['plugins'] ) ) {
			return rest_ensure_response( [ 'success' => true ] );
		}

		$retval = [
			'success'      => true,
			'requirements' => [
				'plugins' => [],
			],
		];

		// Check if the module requirements are met.
		switch_to_blog( $destination_site_id );

		$active_plugins = get_option( 'active_plugins' );
		if ( ! is_array( $active_plugins ) ) {
			$active_plugins = [];
		}

		$required_plugin_keys = array_keys( $module_requirements['plugins'] );
		$missing_plugins      = array_diff( $required_plugin_keys, $active_plugins );

		if ( ! empty( $missing_plugins ) ) {
			$missing_plugin_names = array_map(
				function ( $plugin_key ) use ( $module_requirements ) {
					return $module_requirements['plugins'][ $plugin_key ];
				},
				$missing_plugins
			);

			$retval['success']                 = false;
			$retval['requirements']['plugins'] = $missing_plugin_names;
		}

		restore_current_blog();

		return rest_ensure_response( $retval );
	}

	/**
	 * Sanitization callback for integer values.
	 *
	 * @param mixed $value Value to sanitize.
	 * @return int
	 */
	public function sanitize_integer( $value ) {
		if ( ! is_numeric( $value ) ) {
			return 0;
		}

		return absint( $value );
	}

	/**
	 * Validation callback for integer values.
	 *
	 * @param mixed           $value   The value to validate.
	 * @param WP_REST_Request $request The request object.
	 * @param string          $param   The parameter name.
	 * @return bool|WP_Error True if the parameter is valid, WP_Error otherwise.
	 */
	public function validate_integer( $value, $request, $param ) {
		if ( ! is_numeric( $value ) ) {
			// translators: 1: parameter name, 2: expected type.
			return new WP_Error( 'rest_invalid_param', sprintf( __( '%1$s is not of type %2$s', 'openlab-modules' ), $param, 'integer' ) );
		}

		return true;
	}
}
