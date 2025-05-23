<?php
/**
 * Definition for module-pages endpoint.
 *
 * @package openlab-modules
 */

namespace OpenLab\Modules\Endpoints;

use WP_REST_Controller;
use WP_REST_Request;
use WP_REST_Server;
use WP_Error;

use OpenLab\Modules\Module;

/**
 * Definition for module-pages endpoint.
 */
class ModulePages extends WP_REST_Controller {
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
			'/module-pages/(?P<module_id>\d+)',
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
		/** @var \WP_REST_Request<array<string,mixed>> $request */
		$module_id = $request->get_param( 'module_id' );
		$pages     = [];

		if ( $module_id && is_numeric( $module_id ) ) {
			$module   = Module::get_instance( (int) $module_id );
			$page_ids = $module ? $module->get_page_ids() : [];

			$pages = [];
			foreach ( $page_ids as $page_id ) {
				$page = get_post( $page_id );

				if ( ! $page ) {
					continue;
				}

				$excerpt = get_the_excerpt( $page_id );
				if ( ! $excerpt ) {
					$excerpt = get_post_field( 'post_content', $page_id );
				}

				$excerpt = wp_trim_words( $excerpt, 20, '...' );

				$edit_url = add_query_arg(
					'classic-editor__forget',
					'true',
					get_edit_post_link( $page_id )
				);

				$pages[ $page_id ] = [
					'id'                => $page_id,
					'title'             => $page->post_title,
					'editUrl'           => $edit_url,
					'excerptForPopover' => $excerpt,
					'url'               => get_permalink( $page_id ),
					'status'            => $page->post_status,
				];
			}
		}

		return rest_ensure_response( $pages );
	}
}
