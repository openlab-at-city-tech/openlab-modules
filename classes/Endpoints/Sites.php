<?php
/**
 * Definition for sites endpoint.
 *
 * @package openlab-modules
 */

namespace OpenLab\Modules\Endpoints;

use WP_Site_Query;

use WP_REST_Controller;
use WP_REST_Server;
use WP_REST_Request;
use WP_REST_Response;
use WP_Error;

/**
 * Definition for my-sites endpoint.
 */
class Sites extends WP_REST_Controller {
	/**
	 * Registers the routes for the my-sites endpoint.
	 *
	 * @return void
	 */
	public function register_routes() {
		$version   = '1';
		$namespace = 'openlab-modules/v' . $version;

		register_rest_route(
			$namespace,
			'/my-sites/',
			[
				[
					'methods'             => WP_REST_Server::READABLE,
					'callback'            => [ $this, 'get_items' ],
					'permission_callback' => [ $this, 'get_items_permissions_check' ],
				],
			]
		);
	}

	/**
	 * Handles fetching from the sites endpoint.
	 *
	 * @param WP_REST_Request $request Request object.
	 * @return WP_REST_Response|WP_Error
	 */
	public function get_items( $request ) {
		$params = $request->get_params();

		$page = isset( $params['page'] ) ? absint( $params['page'] ) : 1;

		$per_page = 25;

		$blogs_of_user = get_blogs_of_user( get_current_user_id() );
		$user_blog_ids = wp_list_pluck( $blogs_of_user, 'userblog_id' );

		$query = new WP_Site_Query(
			[
				'number'     => $per_page,
				'offset'     => ( $page - 1 ) * $per_page,
				'site__in'   => $user_blog_ids,
				// phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_query
				'meta_query' => [
					[
						'key'     => 'openlab_modules_active',
						'compare' => 'EXISTS',
					],
				],
			]
		);

		$retval = [
			'results'    => [],
			'pagination' => [
				'more' => false,
			],
		];

		foreach ( $query->sites as $site ) {
			// Only show for users with edit_others_posts capability.
			if ( ! current_user_can_for_blog( $site->blog_id, 'edit_others_posts' ) ) {
				continue;
			}

			$label = sprintf(
				// translators: 1. Numeric ID of site, 2. Name of site, 3. URL of site.
				__( '#%1$s %2$s (%3$s)', 'openlab-modules' ),
				$site->blog_id,
				$site->blogname,
				$site->siteurl
			);

			$retval['results'][] = [
				'url'  => get_home_url( $site->blog_id ),
				'text' => $label,
				'id'   => $site->blog_id,
			];
		}

		if ( $query->max_num_pages > $page ) {
			$retval['pagination'] = [
				'more' => true,
			];
		}

		return rest_ensure_response( $retval );
	}

	/**
	 * Permissions callback for fetching from the sites endpoint.
	 *
	 * @param WP_REST_Request $request Request object.
	 * @return bool|WP_Error
	 */
	public function get_items_permissions_check( $request ) {
		if ( ! is_user_logged_in() ) {
			return new WP_Error( 'rest_forbidden', __( 'You are not currently logged in.', 'openlab-modules' ), [ 'status' => 401 ] );
		}

		return true;
	}
}
