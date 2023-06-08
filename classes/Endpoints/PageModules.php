<?php
/**
 * Definition for page-modules endpoint.
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
 * Definition for module-pages endpoint.
 */
class PageModules extends WP_REST_Controller {
	/**
	 * Registers the routes for the module-pages endpoint.
	 *
	 * @return void
	 */
	public function register_routes() {
		$version   = '1';
		$namespace = 'openlab-modules/v' . $version;

		register_rest_route(
			$namespace,
			'/page-modules/(?P<page_id>\d+)',
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
	 * Permissions callback for reading from the module-pages endpoint.
	 *
	 * @param object $request Request object.
	 * @return bool
	 */
	public function get_item_permission_callback( $request ) {
		// @todo This needs improvement.
		return current_user_can( 'edit_others_posts' );
	}

	/**
	 * Handles fetching from the module-pages endpoint.
	 *
	 * @param object $request Request object.
	 * @return \WP_REST_Response|\WP_Error
	 */
	public function get_item( $request ) {
		// phpcs:ignore Generic.Commenting.DocComment.MissingShort
		/** @var \WP_REST_Request $request */
		$page_id = $request->get_param( 'page_id' );
		$modules = [];

		if ( $page_id && is_numeric( $page_id ) ) {
			$module_ids = Module::get_module_ids_of_page( (int) $page_id );

			$modules = array_map(
				function( $module_id ) {
					$post = get_post( $module_id );

					if ( ! $post ) {
						return;
					}

					return [
						'id'      => $module_id,
						'title'   => $post->post_title,
						'editUrl' => get_edit_post_link( $post_id ),
						'url'     => get_permalink( $post_id ),
					];

				},
				$module_ids
			);
		}

		return rest_ensure_response( array_filter( $modules ) );
	}
}
