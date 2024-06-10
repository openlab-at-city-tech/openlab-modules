<?php
/**
 * Integration for webwork-problem-embed.
 *
 * @package openlab-modules
 */

namespace OpenLab\Modules\Integrations;

use OpenLab\Modules\Editor;
use OpenLab\Modules\Module;
use OpenLab\Modules\Schema;

/**
 * Integration for webwork-problem-embed.
 */
class WWPE {
	/**
	 * Private constructor.
	 *
	 * @return void
	 */
	private function __construct() {}

	/**
	 * Gets the singleton instance.
	 *
	 * @return \OpenLab\Modules\Integrations\WWPE
	 */
	public static function get_instance() {
		static $instance;

		if ( empty( $instance ) ) {
			$instance = new self();
		}

		return $instance;
	}

	/**
	 * Initializes the integration.
	 *
	 * @return void
	 */
	public function init() {
		add_action( 'wp_enqueue_scripts', [ $this, 'enqueue_assets' ], 100 );
		add_filter( 'openlab_modules_module_requirements', [ $this, 'add_wwpe_requirement' ], 10, 2 );
	}

	/**
	 * Sets up the post for use with WWPE.
	 *
	 * @return void
	 */
	public function enqueue_assets() {
		// Only set up data for modules or posts belonging to modules.
		if ( ! $this->is_module_page() ) {
			return;
		}

		$blocks_asset_file = Editor::get_blocks_asset_file( 'webwork-problem-embed' );

		wp_enqueue_script(
			'openlab-modules-wwpe',
			OPENLAB_MODULES_PLUGIN_URL . '/build/webwork-problem-embed.js',
			[ 'wwpe-public' ],
			$blocks_asset_file['version'],
			true
		);

		wp_localize_script(
			'openlab-modules-wwpe',
			'openlabModulesWwpeStrings',
			[
				'continueWithout'   => __( 'Continue without logging in', 'openlab-modules' ),
				'dismiss'           => __( 'Dismiss', 'openlab-modules' ),
				'logIn'             => __( 'Log In', 'openlab-modules' ),
				'sectionComplete'   => __( 'You have completed this page. You will receive a private message confirming the completion.', 'openlab-modules' ),
				'toReceiveCredit'   => __( 'To receive an official confirmation when you complete this page, please sign in now.', 'openlab-modules' ),
				'youAreNotLoggedIn' => __( 'You are not logged in.', 'openlab-modules' ),
			]
		);

		$current_page_permalink = get_permalink();

		wp_add_inline_script(
			'openlab-modules-wwpe',
			'const openlabModulesWwpe = ' . wp_json_encode(
				[
					'ajaxUrl'        => admin_url( 'admin-ajax.php' ),
					'isUserLoggedIn' => is_user_logged_in(),
					'loginUrl'       => wp_login_url( (string) $current_page_permalink ),
					'nonce'          => wp_create_nonce( 'openlab-modules' ),
					'postId'         => get_queried_object_id(),
				]
			),
			'before'
		);

		wp_enqueue_style(
			'openlab-modules-wwpe',
			OPENLAB_MODULES_PLUGIN_URL . '/build/webwork-problem-embed.css',
			[],
			$blocks_asset_file['version']
		);
	}

	/**
	 * Detects and adds the WWPE requirement for a module.
	 *
	 * @param string[] $plugin_requirements Array of plugin requirements.
	 * @param int      $module_id           Module ID.
	 * @return string[]
	 */
	public function add_wwpe_requirement( $plugin_requirements, $module_id ) {
		// Look for the 'webwork_problem' shortcode in the content of the module and its pages.
		$contains_wwpe = false;

		$module = Module::get_instance( $module_id );
		if ( ! $module ) {
			return $plugin_requirements;
		}

		$contains_wwpe = $this->post_contains_wwpe( $module_id );

		if ( ! $contains_wwpe ) {
			$module_page_ids = $module->get_page_ids( 'all' );
			foreach ( $module_page_ids as $page_id ) {
				if ( $this->post_contains_wwpe( $page_id ) ) {
					$contains_wwpe = true;
					break;
				}
			}
		}

		if ( ! $contains_wwpe ) {
			return $plugin_requirements;
		}

		$plugin_requirements['webwork-problem-embed/webwork-problem-embed.php'] = __( 'WebWoRK Problem Embed', 'openlab-modules' );

		return $plugin_requirements;
	}

	/**
	 * Determine whether a post contains WWPE content.
	 *
	 * We look for the 'webwork_problem' shortcode or the wwpe/problem-embed block in the
	 * post content.
	 *
	 * @param int $post_id Post ID.
	 * @return bool
	 */
	private function post_contains_wwpe( $post_id ) {
		$post = get_post( $post_id );
		if ( ! $post ) {
			return false;
		}

		if ( has_shortcode( $post->post_content, 'webwork_problem' ) ) {
			return true;
		}

		$blocks = parse_blocks( $post->post_content );
		foreach ( $blocks as $block ) {
			if ( 'wwpe/problem-embed' === $block['blockName'] ) {
				return true;
			}
		}

		return false;
	}

	/**
	 * Determine whether the current page is a module, or a "section" page belonging to a module.
	 *
	 * @return bool
	 */
	private function is_module_page() {
		$is_module_page = false;
		$post           = get_post();
		if ( is_singular( Schema::get_module_post_type() ) ) {
			$is_module_page = true;
		} elseif ( is_singular() ) {
			$module_ids     = $post ? Module::get_module_ids_of_page( $post->ID ) : [];
			$is_module_page = ! empty( $module_ids );
		}

		return $is_module_page;
	}
}
