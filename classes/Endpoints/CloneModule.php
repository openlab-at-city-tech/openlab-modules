<?php
/**
 * Definition of clone-module endpoint.
 *
 * @package openlab-modules
 */

namespace OpenLab\Modules\Endpoints;

use WP_REST_Controller;
use WP_REST_Request;
use WP_REST_Server;
use WP_Error;

use OpenLab\Modules\Module;
use OpenLab\Modules\Cloner;

/**
 * Definition for clone-module endpoint.
 */
class CloneModule extends WP_REST_Controller {
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
			'/clone-module/(?P<module_id>\d+)',
			[
				[
					'methods'             => WP_REST_Server::CREATABLE,
					'callback'            => [ $this, 'create_item' ],
					'permission_callback' => [ $this, 'create_item_permission_callback' ],
					'args'                => [
						'destinationSiteId' => [
							'type'              => 'integer',
							'required'          => true,
							'sanitize_callback' => [ $this, 'sanitize_integer' ],
							'validate_callback' => [ $this, 'validate_integer' ],
						],
						'module_id'         => [
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
	 * Permissions callback for creating from the clone-module endpoint.
	 *
	 * @param WP_REST_Request $request Request object.
	 * @return bool|WP_Error
	 */
	public function create_item_permission_callback( $request ) {
		$destination_site_id = $this->sanitize_integer( $request->get_param( 'destinationSiteId' ) );

		if ( ! $destination_site_id ) {
			return new WP_Error( 'missing_destination_site_id', __( 'Destination site ID is required.', 'openlab-modules' ), [ 'status' => 400 ] );
		}

		// Verify that sharing is enabled for the module.
		$module_id = $this->sanitize_integer( $request->get_param( 'module_id' ) );

		$module = Module::get_instance( $module_id );
		if ( ! $module ) {
			return new WP_Error( 'module_not_found', __( 'Module not found.', 'openlab-modules' ), [ 'status' => 404 ] );
		}

		if ( ! $module->is_sharing_enabled() ) {
			return new WP_Error( 'module_not_shared', __( 'Module is not shared.', 'openlab-modules' ), [ 'status' => 400 ] );
		}

		$error = null;

		switch_to_blog( $destination_site_id );

		// @todo This should reference the cap for the post type.
		if ( ! current_user_can( 'edit_posts' ) ) {
			$error = new WP_Error( 'rest_forbidden', __( 'You do not have permission to create a module on this site.', 'openlab-modules' ), [ 'status' => 403 ] );
		}

		$active_plugins = (array) get_option( 'active_plugins' );
		if ( ! in_array( 'openlab-modules/openlab-modules.php', $active_plugins, true ) ) {
			$error = new WP_Error( 'rest_forbidden', __( 'OpenLab Modules must be active on the destination site.', 'openlab-modules' ), [ 'status' => 403 ] );
		}

		restore_current_blog();

		if ( $error ) {
			return $error;
		}

		return true;
	}

	/**
	 * Handles creating from the clone-module endpoint.
	 *
	 * @param WP_REST_Request $request Request object.
	 * @return \WP_REST_Response|\WP_Error
	 */
	public function create_item( $request ) {
		$destination_site_id = $this->sanitize_integer( $request->get_param( 'destinationSiteId' ) );
		$module_id           = $this->sanitize_integer( $request->get_param( 'module_id' ) );

		$response = [
			'clone_url' => '',
			'message'   => '',
			'success'   => false,
		];

		$module_data = Cloner::get_module_data( $module_id );
		if ( is_wp_error( $module_data ) ) {
			$response['message'] = $module_data->get_error_message();
			return rest_ensure_response( $response );
		}

		$clone_results = Cloner::import_module_to_site( $module_data, $destination_site_id );

		if ( is_wp_error( $clone_results ) ) {
			$response['message'] = $clone_results->get_error_message();
			return rest_ensure_response( $response );
		}

		$response['success']   = true;
		$response['clone_url'] = $clone_results['clone_url'];

		return rest_ensure_response( $response );
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
