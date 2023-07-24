<?php
/**
 * Handles frontend integration.
 *
 * @package openlab-modules
 */

namespace OpenLab\Modules;

/**
 * Handles frontend integration.
 */
class Frontend {
	/**
	 * Private constructor.
	 *
	 * @return void
	 */
	private function __construct() {}

	/**
	 * Gets the singleton instance.
	 *
	 * @return \OpenLab\Modules\Frontend
	 */
	public static function get_instance() {
		static $instance;

		if ( empty( $instance ) ) {
			$instance = new self();
		}

		return $instance;
	}

	/**
	 * Initializes frontend integration.
	 *
	 * @return void
	 */
	public function init() {
		add_action( 'wp_enqueue_scripts', [ $this, 'register_assets' ] );

		add_filter( 'the_content', [ __CLASS__, 'append_pagination' ] );
	}

	/**
	 * Registers assets for use on the frontend.
	 *
	 * @return void
	 */
	public function register_assets() {
		$blocks_asset_file = Editor::get_blocks_asset_file();

		wp_register_style(
			'openlab-modules-frontend',
			OPENLAB_MODULES_PLUGIN_URL . '/build/frontend.css',
			[],
			$blocks_asset_file['version']
		);
	}

	/**
	 * Appends module pagination.
	 *
	 * @param string $content Page content.
	 * @return string
	 */
	public static function append_pagination( $content ) {
		// Only run once per pageload. If another plugin interferes, there's nothing we can do.
		remove_filter( 'the_content', [ __CLASS__, 'append_pagination' ] );

		if ( ! is_main_query() ) {
			return $content;
		}

		if ( ! is_singular() ) {
			return $content;
		}

		$module_id = null;
		if ( Schema::get_module_post_type() === get_post_type() ) {
			$is_module = true;
			$module_id = get_queried_object_id();
		} else {
			$is_module  = false;
			$module_ids = Module::get_module_ids_of_page( get_queried_object_id() );
			if ( $module_ids ) {
				$module_id = $module_ids[0];
			}
		}

		if ( ! $module_id ) {
			return $content;
		}

		$module = Module::get_instance( $module_id );
		if ( ! $module ) {
			return $content;
		}

		// Offset keys so that the module page is 0.
		$module_page_ids = $module->get_page_ids();
		$all_page_ids    = array_merge( [ $module_id ], $module_page_ids );

		// Don't include non-published posts in pagination.
		$all_page_ids = array_filter(
			$all_page_ids,
			function( $page_id ) {
				return 'publish' === get_post_status( $page_id );
			}
		);

		$current_index = $is_module ? 0 : array_search( get_queried_object_id(), $all_page_ids, true );

		if ( false === $current_index ) {
			return $content;
		}

		$current_index = (int) $current_index;

		$neighbors = [
			'prev' => $current_index > 0 ? $all_page_ids[ $current_index - 1 ] : null,
			'next' => ( $current_index + 1 ) < count( $all_page_ids ) ? $all_page_ids[ $current_index + 1 ] : null,
		];

		$links = [
			'prev' => null,
			'next' => null,
		];

		foreach ( $neighbors as $neighbor_type => $neighbor_id ) {
			if ( null === $neighbor_id ) {
				continue;
			}

			$page_title = $neighbor_id === $module_id ? __( 'Module Home', 'openlab-modules' ) : (string) get_the_title( $neighbor_id );

			$links[ $neighbor_type ] = [
				'title' => $page_title,
				'url'   => (string) get_permalink( $neighbor_id ),
			];
		}

		$prev_link = '';
		if ( $links['prev'] ) {
			$prev_link = sprintf(
				// translators: Title of previous post.
				esc_html__( 'Previous: %s', 'openlab-modules' ),
				sprintf(
					'<a href="%s">%s</a>',
					esc_url( $links['prev']['url'] ),
					esc_html( $links['prev']['title'] )
				)
			);
		}

		$next_link = '';
		if ( $links['next'] ) {
			$next_link = sprintf(
				// translators: Title of next post.
				esc_html__( 'Next: %s', 'openlab-modules' ),
				sprintf(
					'<a href="%s">%s</a>',
					esc_url( $links['next']['url'] ),
					esc_html( $links['next']['title'] )
				)
			);
		}

		$pagination = sprintf(
			'
			<div class="module-pagination">
				<div class="module-pagination-previous">%s</div>

				<div class="module-pagination-next">%s</div>
			</div>
			',
			$prev_link,
			$next_link
		);

		wp_enqueue_style( 'openlab-modules-frontend' );

		return $content . $pagination;
	}
}
