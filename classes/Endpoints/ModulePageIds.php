<?php
/**
 * Definition for module-page-ids endpoint.
 *
 * @package openlab-modules
 */

namespace OpenLab\Modules\Endpoints;

use \WP_REST_Controller;
use \WP_REST_Request;
use \WP_REST_Server;
use \WP_Error;

use \OpenLab\Modules\Module;

/**
 * Definition for module-page-ids endpoint.
 */
class ModulePageIds extends WP_REST_Controller {
	/**
	 * Registers the routes for the module-page-ids endpoint.
	 *
	 * @return void
	 */
	public function register_routes() {
		$version   = '1';
		$namespace = 'openlab-modules/v' . $version;

		register_rest_route(
			$namespace,
			'/module-page-ids/(?P<module_id>\d+)',
			[
				[
					'methods'             => WP_REST_Server::READABLE,
					'callback'            => [ $this, 'get_item' ],
					'permission_callback' => [ $this, 'get_item_permission_callback' ],
				],
			]
		);
	}

	/**
	 * Permissions callback for reading from the module-page-ids endpoint.
	 *
	 * @param object $request Request object.
	 * @return bool
	 */
	public function get_item_permission_callback( $request ) {
		// @todo This needs improvement.
		return current_user_can( 'edit_others_posts' );
	}

	/**
	 * Handles fetching from the module-page-ids endpoint.
	 *
	 * @param object $request Request object.
	 * @return \WP_REST_Response|\WP_Error
	 */
	public function get_item( $request ) {
		// phpcs:ignore Generic.Commenting.DocComment.MissingShort
		/** @var \WP_REST_Request $request */
		$module_id = $request->get_param( 'module_id' );
		$page_ids  = [];

		if ( $module_id && is_numeric( $module_id ) ) {
			$module   = Module::get_instance( (int) $module_id );
			$page_ids = $module ? $module->get_page_ids() : [];
		}

		return rest_ensure_response( $page_ids );
	}
}
